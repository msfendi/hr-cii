<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Candidate Status Dashboard - RecruitPort</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "outline-variant": "#c4c6d5",
                        "on-tertiary-fixed": "#2e1500",
                        "inverse-surface": "#2f3037",
                        "surface-dim": "#dad9e3",
                        "on-secondary-fixed-variant": "#444653",
                        "outline": "#747684",
                        "secondary-fixed-dim": "#c4c5d6",
                        "on-primary": "#ffffff",
                        "inverse-primary": "#b5c4ff",
                        "primary": "#2b54bf",
                        "inverse-on-surface": "#f1f0f9",
                        "surface-container-highest": "#e2e1eb",
                        "tertiary-fixed": "#ffdcc2",
                        "on-background": "#1a1b22",
                        "secondary": "#5b5e6c",
                        "secondary-container": "#e0e1f2",
                        "primary-container": "#496eda",
                        "primary-fixed": "#dbe1ff",
                        "on-primary-fixed-variant": "#053da9",
                        "on-tertiary-container": "#fffbff",
                        "surface-tint": "#2e57c2",
                        "tertiary-fixed-dim": "#ffb77c",
                        "surface-bright": "#faf8ff",
                        "on-secondary-fixed": "#181b27",
                        "tertiary-container": "#b06000",
                        "surface-variant": "#e2e1eb",
                        "surface-container-high": "#e8e7f1",
                        "surface": "#faf8ff",
                        "on-secondary-container": "#616472",
                        "on-primary-fixed": "#00174d",
                        "on-surface": "#1a1b22",
                        "error-container": "#ffdad6",
                        "on-tertiary-fixed-variant": "#6d3900",
                        "on-tertiary": "#ffffff",
                        "surface-container": "#eeedf7",
                        "surface-container-low": "#f3f3fc",
                        "on-error": "#ffffff",
                        "error": "#ba1a1a",
                        "secondary-fixed": "#e0e1f2",
                        "on-secondary": "#ffffff",
                        "on-primary-container": "#fefcff",
                        "on-surface-variant": "#434653",
                        "background": "#faf8ff",
                        "on-error-container": "#93000a",
                        "tertiary": "#8c4b00",
                        "surface-container-lowest": "#ffffff",
                        "primary-fixed-dim": "#b5c4ff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "sidebar-collapsed": "104px",
                        "card-spacer": "1.25rem",
                        "sidebar-width": "224px",
                        "container-padding": "1.5rem",
                        "gutter": "1.5rem"
                    },
                    "fontFamily": {
                        "headline-lg": ["Nunito Sans"],
                        "label-lg": ["Nunito Sans"],
                        "headline-xl": ["Nunito Sans"],
                        "body-lg": ["Nunito Sans"],
                        "label-md": ["Nunito Sans"],
                        "headline-md": ["Nunito Sans"],
                        "body-md": ["Nunito Sans"]
                    },
                    "fontSize": {
                        "headline-lg": ["1.5rem", { "lineHeight": "1.2", "fontWeight": "600" }],
                        "label-lg": ["0.75rem", { "lineHeight": "1", "fontWeight": "700" }],
                        "headline-xl": ["1.75rem", { "lineHeight": "1.2", "fontWeight": "700" }],
                        "body-lg": ["1rem", { "lineHeight": "1.5", "fontWeight": "400" }],
                        "label-md": ["0.7rem", { "lineHeight": "1", "fontWeight": "600" }],
                        "headline-md": ["1.25rem", { "lineHeight": "1.2", "fontWeight": "600" }],
                        "body-md": ["0.875rem", { "lineHeight": "1.5", "fontWeight": "400" }]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Nunito Sans', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        .custom-card-shadow {
            box-shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, .15);
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.35rem;
            font-weight: 700;
            font-size: 0.75rem;
        }

        .timeline-line {
            position: absolute;
            top: 24px;
            left: 24px;
            bottom: 24px;
            width: 2px;
            background-color: #e2e1eb;
            z-index: 0;
        }
    </style>
</head>

<body class="bg-surface-container-low text-on-surface">
    <!-- TopNavBar -->
    @include('layouts_recruitments.partials._header', ['hideStep' => true])
    <div class="flex min-h-[calc(100vh-120px)]">
        <main class="flex-1 p-container-padding max-w-6xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <nav aria-label="Breadcrumb" class="flex text-label-md font-label-md text-secondary mb-2">
                    <ol class="flex items-center space-x-2">
                        <li class=""><a class="hover:text-primary" href="{{ route('portal.recruitment-status.index') }}">Candidate Portal</a></li>
                        <li><span class="material-symbols-outlined text-[14px]">chevron_right</span></li>
                        <li class="text-primary font-bold">Application Status</li>
                    </ol>
                </nav>
                <h1 class="font-headline-xl text-headline-xl text-on-surface">Status Lamaran</h1>
            </div>
            <div class="grid grid-cols-1 gap-gutter">
                <!-- Left: Candidate Info & Progress -->
                <div class="space-y-6">
                    <!-- Personal Info Card -->
                    <div class="bg-surface-container-lowest p-6 rounded-xl custom-card-shadow md:p-8">
                        <div class="flex items-start justify-between mb-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-primary-container rounded-xl flex items-center justify-center">
                                    <span class="material-symbols-outlined text-on-primary-container text-4xl">person</span>
                                </div>
                                <div>
                                    <h2 class="text-headline-lg font-bold text-on-surface">{{ $detail->NAMA }}</h2>
                                    <p class="text-body-md text-secondary">{{ $detail->jabatan ?? '-' }}</p>
                                </div>
                            </div>
                            @php
                                $statusApply = strtoupper($detail->status_apply ?? 'APPLIED');
                            @endphp
                            @if($statusApply === 'REJECTED')
                                <span class="status-badge bg-error-container text-error">Rejected</span>
                            @elseif($statusApply === 'ACCEPTED' || $statusApply === 'ONBOARDING')
                                <span class="status-badge bg-green-100 text-green-700">{{ $statusApply }}</span>
                            @else
                                <span class="status-badge bg-blue-100 text-blue-700">{{ $statusApply }}</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pt-6 border-t border-outline-variant">
                            <div>
                                <p class="text-label-md text-secondary uppercase font-bold tracking-wider mb-1">NIK</p>
                                <p class="text-body-md font-semibold">{{ $detail->NIK }}</p>
                            </div>
                            <div>
                                <p class="text-label-md text-secondary uppercase font-bold tracking-wider mb-1">Applied Date</p>
                                <p class="text-body-md font-semibold">{{ \Carbon\Carbon::parse($detail->created_at)->format('d M Y') }}</p>
                            </div>
                            <div>
                                <p class="text-label-md text-secondary uppercase font-bold tracking-wider mb-1">Department</p>
                                <p class="text-body-md font-semibold">{{ $detail->department ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-label-md text-secondary uppercase font-bold tracking-wider mb-1">Asal Kota</p>
                                <p class="text-body-md font-semibold">{{ $detail->KABUPATEN ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- Progress Tracker Card -->
                    <div class="bg-surface-container-lowest p-6 rounded-xl custom-card-shadow md:p-8">
                        <h3 class="text-headline-md font-bold text-primary mb-8">Timeline Proses Seleksi</h3>
                        <div class="relative pl-12 space-y-10">
                            <div class="timeline-line"></div>
                            <!-- Application Step (Always complete) -->
                            <div class="relative">
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-green-600 rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">check</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Lamaran Dikirim</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ \Carbon\Carbon::parse($detail->created_at)->format('d M Y') }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-green-600 font-bold">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <span class="">Submitted</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Step: Interview (INTV.) -->
                            <div class="relative">
                                @if($detail->result_interview === 'TRUE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-green-600 rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">check</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Interview HR</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ $detail->tgl_interview ? \Carbon\Carbon::parse($detail->tgl_interview)->format('d M Y') : '-' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-green-600 font-bold">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <span class="">Lolos</span>
                                    </div>
                                </div>
                                @elseif($detail->result_interview === 'FALSE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-error rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">close</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">HR &amp; Technical Interview (INTV.)</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ $detail->tgl_interview ? \Carbon\Carbon::parse($detail->tgl_interview)->format('d M Y') : '-' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-error font-bold">
                                        <span class="material-symbols-outlined">cancel</span>
                                        <span class="">Tidak Lolos</span>
                                    </div>
                                </div>
                                @else
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-outline-variant rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">hourglass_empty</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="opacity-50">
                                        <h4 class="font-bold text-body-lg">HR &amp; Technical Interview (INTV.)</h4>
                                        <p class="text-body-md text-secondary">{{ $detail->tgl_interview ? 'Scheduled on ' . \Carbon\Carbon::parse($detail->tgl_interview)->format('d M Y') : 'Waiting schedule' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-outline font-bold opacity-50">
                                        <span class="material-symbols-outlined">pending</span>
                                        <span class="">Belum Dinilai</span>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Step: Health Screening (KES.) -->
                            <div class="relative">
                                @if($detail->result_kesehatan === 'TRUE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-green-600 rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">check</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Tes Kesehatan</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ $detail->tgl_kesehatan ? \Carbon\Carbon::parse($detail->tgl_kesehatan)->format('d M Y') : '-' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-green-600 font-bold">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <span class="">Lolos</span>
                                    </div>
                                </div>
                                @elseif($detail->result_kesehatan === 'FALSE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-error rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">close</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Health Screening (KES.)</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ $detail->tgl_kesehatan ? \Carbon\Carbon::parse($detail->tgl_kesehatan)->format('d M Y') : '-' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-error font-bold">
                                        <span class="material-symbols-outlined">cancel</span>
                                        <span class="">Tidak Lolos</span>
                                    </div>
                                </div>
                                @else
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-outline-variant rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">hourglass_empty</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="opacity-50">
                                        <h4 class="font-bold text-body-lg">Health Screening (KES.)</h4>
                                        <p class="text-body-md text-secondary">{{ $detail->tgl_kesehatan ? 'Scheduled on ' . \Carbon\Carbon::parse($detail->tgl_kesehatan)->format('d M Y') : 'Waiting schedule' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-outline font-bold opacity-50">
                                        <span class="material-symbols-outlined">pending</span>
                                        <span class="">Belum Dinilai</span>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Step: Technical Assessment (Test) -->
                            <div class="relative">
                                @if($detail->result_test === 'TRUE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-green-600 rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">check</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Tes Teknikal</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ $detail->tgl_test ? \Carbon\Carbon::parse($detail->tgl_test)->format('d M Y') : '-' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-green-600 font-bold">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <span class="">Lolos</span>
                                    </div>
                                </div>
                                @elseif($detail->result_test === 'FALSE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-error rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">close</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Technical Assessment (Test)</h4>
                                        <p class="text-body-md text-secondary">Completed on {{ $detail->tgl_test ? \Carbon\Carbon::parse($detail->tgl_test)->format('d M Y') : '-' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-error font-bold">
                                        <span class="material-symbols-outlined">cancel</span>
                                        <span class="">Tidak Lolos</span>
                                    </div>
                                </div>
                                @else
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-outline-variant rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">hourglass_empty</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="opacity-50">
                                        <h4 class="font-bold text-body-lg">Technical Assessment (Test)</h4>
                                        <p class="text-body-md text-secondary">{{ $detail->tgl_test ? 'Scheduled on ' . \Carbon\Carbon::parse($detail->tgl_test)->format('d M Y') : 'Waiting schedule' }}</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-outline font-bold opacity-50">
                                        <span class="material-symbols-outlined">pending</span>
                                        <span class="">Belum Dinilai</span>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Step: User Final Review -->
                            <div class="relative">
                                @if($detail->result_user === 'TRUE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-green-600 rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">check</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Review Interview User</h4>
                                        <p class="text-body-md text-secondary">Finalized</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-green-600 font-bold">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <span class="">Lolos</span>
                                    </div>
                                </div>
                                @elseif($detail->result_user === 'FALSE')
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-error rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">close</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">User Final Review</h4>
                                        <p class="text-body-md text-secondary">Finalized</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-error font-bold">
                                        <span class="material-symbols-outlined">cancel</span>
                                        <span class="">Tidak Lolos</span>
                                    </div>
                                </div>
                                @else
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-outline-variant rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">hourglass_empty</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="opacity-50">
                                        <h4 class="font-bold text-body-lg">User Final Review</h4>
                                        <p class="text-body-md text-secondary">Awaiting result</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-outline font-bold opacity-50">
                                        <span class="material-symbols-outlined">pending</span>
                                        <span class="">Belum Dinilai</span>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Step: Final Status -->
                            @if ($detail->status_apply === 'ACCEPTED' || $detail->status_apply === 'ONBOARDING')
                            <div class="relative">
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-green-600 rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">check</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Hasil Akhir</h4>
                                        <p class="text-body-md text-secondary">Finalized</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-green-600 font-bold">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <span class="">{{ $detail->status_apply }}</span>
                                    </div>
                                </div>
                            </div>
                            @elseif ($detail->status_apply === 'REJECTED')
                            <div class="relative">
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-error rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">close</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-body-lg">Hasil Akhir</h4>
                                        <p class="text-body-md text-secondary">Finalized</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-error font-bold">
                                        <span class="material-symbols-outlined">cancel</span>
                                        <span class="">TIDAK LOLOS</span>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="relative">
                                <div class="absolute -left-12 mt-1 w-6 h-6 bg-outline-variant rounded-full flex items-center justify-center z-10">
                                    <span class="material-symbols-outlined text-white text-[16px]">hourglass_empty</span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="opacity-50">
                                        <h4 class="font-bold text-body-lg">Hasil Akhir</h4>
                                        <p class="text-body-md text-secondary">Awaiting overall result</p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center gap-2 text-outline font-bold opacity-50">
                                        <span class="material-symbols-outlined">remove_circle_outline</span>
                                        <span class="">Belum Dinilai</span>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- Right: Instructions & Sidebar -->

            </div>
        </main>
    </div>
    <!-- Footer Section -->
    @include('layouts_recruitments.partials._footer')


</body>

</html>