<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RecruitFlow - Check Application Status</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&amp;display=swap"
        rel="stylesheet" />
    <!-- Material Symbols -->
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary-container": "#496eda",
                        "on-surface-variant": "#434653",
                        "on-error": "#ffffff",
                        "on-secondary-fixed": "#181b27",
                        "inverse-surface": "#2f3037",
                        "tertiary": "#8c4b00",
                        "tertiary-container": "#b06000",
                        "on-primary": "#ffffff",
                        "error-container": "#ffdad6",
                        "surface-container-low": "#f3f3fc",
                        "on-tertiary": "#ffffff",
                        "surface-tint": "#2e57c2",
                        "secondary": "#5b5e6c",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-fixed": "#2e1500",
                        "on-tertiary-container": "#fffbff",
                        "surface-container-highest": "#e2e1eb",
                        "on-tertiary-fixed-variant": "#6d3900",
                        "surface-bright": "#faf8ff",
                        "on-secondary-fixed-variant": "#444653",
                        "on-primary-container": "#fefcff",
                        "primary-fixed": "#dbe1ff",
                        "on-error-container": "#93000a",
                        "secondary-container": "#e0e1f2",
                        "background": "#faf8ff",
                        "on-primary-fixed": "#00174d",
                        "error": "#ba1a1a",
                        "surface-variant": "#e2e1eb",
                        "on-secondary": "#ffffff",
                        "tertiary-fixed-dim": "#ffb77c",
                        "on-secondary-container": "#616472",
                        "on-primary-fixed-variant": "#053da9",
                        "outline-variant": "#c4c6d5",
                        "tertiary-fixed": "#ffdcc2",
                        "surface-dim": "#dad9e3",
                        "secondary-fixed": "#e0e1f2",
                        "inverse-on-surface": "#f1f0f9",
                        "surface": "#faf8ff",
                        "primary-fixed-dim": "#b5c4ff",
                        "secondary-fixed-dim": "#c4c5d6",
                        "surface-container": "#eeedf7",
                        "on-surface": "#1a1b22",
                        "inverse-primary": "#b5c4ff",
                        "surface-container-high": "#e8e7f1",
                        "outline": "#747684",
                        "on-background": "#1a1b22",
                        "primary": "#2b54bf"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "sidebar-collapsed": "104px",
                        "gutter": "1.5rem",
                        "card-spacer": "1.25rem",
                        "sidebar-width": "224px",
                        "container-padding": "1.5rem"
                    },
                    "fontFamily": {
                        "label-md": ["Nunito Sans", "sans-serif"],
                        "headline-lg": ["Nunito Sans", "sans-serif"],
                        "body-md": ["Nunito Sans", "sans-serif"],
                        "headline-xl": ["Nunito Sans", "sans-serif"],
                        "headline-md": ["Nunito Sans", "sans-serif"],
                        "body-lg": ["Nunito Sans", "sans-serif"],
                        "label-lg": ["Nunito Sans", "sans-serif"]
                    },
                    "fontSize": {
                        "label-md": ["0.7rem", { "lineHeight": "1", "fontWeight": "600" }],
                        "headline-lg": ["1.5rem", { "lineHeight": "1.2", "fontWeight": "600" }],
                        "body-md": ["0.875rem", { "lineHeight": "1.5", "fontWeight": "400" }],
                        "headline-xl": ["1.75rem", { "lineHeight": "1.2", "fontWeight": "700" }],
                        "headline-md": ["1.25rem", { "lineHeight": "1.2", "fontWeight": "600" }],
                        "body-lg": ["1rem", { "lineHeight": "1.5", "fontWeight": "400" }],
                        "label-lg": ["0.75rem", { "lineHeight": "1", "fontWeight": "700" }]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Nunito Sans', sans-serif;
            background-color: #faf8ff;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .card-shadow {
            box-shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, .15);
        }
    </style>
</head>

<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- TopNavBar -->
    @include('layouts_recruitments.partials._header', ['hideStep' => true])
    <!-- Main Content Area -->
    <main class="flex-grow pt-24 pb-12 flex items-center justify-center px-4">
        <div class="w-full max-w-lg">
            <!-- Breadcrumb / Context -->
            <div class="mb-8 text-center">
                <h1 class="font-headline-xl text-headline-xl text-primary mb-2">Cek Status Seleksi</h1>
                <p class="font-body-md text-body-md text-on-surface-variant max-w-sm mx-auto">
                    Silakan masukkan NIK dan tanggal pendaftaran Anda untuk melihat hasil seleksi.
                </p>
            </div>
            <!-- Portal Card -->
            <div
                class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant overflow-hidden">
                <!-- Card Accent Header -->
                <div class="h-1.5 bg-primary w-full"></div>
                <form class="p-8 space-y-6" id="statusForm" action="{{ route('portal.recruitment-status.check') }}" method="POST">
                    @csrf
                    
                    @if(session('error'))
                        <div class="p-4 mb-4 bg-error-container text-error rounded-lg text-body-md border border-error/20 flex gap-2 items-center">
                            <span class="material-symbols-outlined" data-icon="error">error</span>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <!-- Input: NIK -->
                    <div class="space-y-2">
                        <label
                            class="block font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider"
                            for="nik">Nomor Induk Kependudukan (NIK)</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-outline"
                                data-icon="badge">badge</span>
                            <input
                                class="w-full pl-11 pr-4 py-3 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 bg-white transition-all outline-none font-body-md text-body-md text-on-surface"
                                id="nik" maxlength="16" name="nik" placeholder="16 digit nomor KTP" required=""
                                type="text" />
                        </div>
                        <p class="text-[0.7rem] text-outline italic">Pastikan NIK sesuai dengan yang terdaftar pada form
                            aplikasi.</p>
                    </div>
                    <!-- Input: Tanggal Pendaftaran -->
                    <div class="space-y-2">
                        <label
                            class="block font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider"
                            for="reg_date">Tanggal Pendaftaran</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-outline"
                                data-icon="calendar_month">calendar_month</span>
                            <input
                                class="w-full pl-11 pr-4 py-3 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 bg-white transition-all outline-none font-body-md text-body-md text-on-surface"
                                id="reg_date" name="reg_date" required="" type="date" />
                        </div>
                    </div>
                    <!-- Action Button -->
                    <div class="pt-2">
                        <button
                            class="w-full bg-primary hover:bg-primary/90 text-on-primary font-bold py-3.5 px-6 rounded-lg shadow-md transition-all duration-200 flex items-center justify-center gap-2 group active:scale-[0.98]"
                            type="submit">
                            <span class="material-symbols-outlined text-[20px]" data-icon="search">search</span>
                            <span class="font-label-lg text-label-lg uppercase">Cek Status Sekarang</span>
                        </button>
                    </div>
                </form>
            </div>
            <!-- Footer Help Links -->
            <div class="mt-8 text-center space-y-4">
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Lupa tanggal pendaftaran atau mengalami kendala?
                </p>
                <div class="flex justify-center gap-4">
                    <a class="text-primary hover:underline font-label-lg text-label-lg flex items-center gap-1"
                        href="#">
                        <span class="material-symbols-outlined text-[16px]" data-icon="help">help</span>
                        Pusat Bantuan
                    </a>
                    <span class="text-outline-variant">|</span>
                    <a class="text-primary hover:underline font-label-lg text-label-lg flex items-center gap-1"
                        href="#">
                        <span class="material-symbols-outlined text-[16px]" data-icon="mail">mail</span>
                        Hubungi HR
                    </a>
                </div>
            </div>
        </div>
    </main>
    <!-- Visual Background Element (Atmospheric) -->
    <div class="fixed bottom-0 left-0 w-full h-1/2 pointer-events-none opacity-20 -z-10">
        <div class="absolute inset-0 bg-gradient-to-t from-primary-fixed to-transparent"></div>
    </div>
    <!-- Footer Section -->
    @include('layouts_recruitments.partials._footer')
    <script>
        // Limit NIK input to digits only
        document.getElementById('nik').addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Add loading state on submit
        document.getElementById('statusForm').addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="material-symbols-outlined animate-spin" data-icon="progress_activity">progress_activity</span><span class="font-label-lg text-label-lg uppercase">Memproses...</span>';
            submitBtn.disabled = true;
        });
    </script>
</body>

</html>