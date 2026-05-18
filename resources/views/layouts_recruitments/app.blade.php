<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>@yield('title', 'RecruitFlow')</title>

    {{-- Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:ital,opsz,wght@0,6..12,200..1000;1,6..12,200..1000&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    {{-- Tailwind Config --}}
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-fixed-dim": "#c4c5d6",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-container": "#b06000",
                        "surface": "#faf8ff",
                        "surface-tint": "#2e57c2",
                        "secondary": "#5b5e6c",
                        "on-surface-variant": "#434653",
                        "on-primary": "#ffffff",
                        "surface-dim": "#dad9e3",
                        "surface-container-highest": "#e2e1eb",
                        "on-surface": "#1a1b22",
                        "inverse-on-surface": "#f1f0f9",
                        "surface-bright": "#faf8ff",
                        "on-error": "#ffffff",
                        "on-tertiary-fixed": "#2e1500",
                        "error": "#ba1a1a",
                        "primary-container": "#496eda",
                        "on-secondary-fixed": "#181b27",
                        "tertiary-fixed-dim": "#ffb77c",
                        "on-primary-fixed-variant": "#053da9",
                        "error-container": "#ffdad6",
                        "surface-container-high": "#e8e7f1",
                        "on-secondary-container": "#616472",
                        "secondary-container": "#e0e1f2",
                        "surface-container-low": "#f3f3fc",
                        "surface-variant": "#e2e1eb",
                        "tertiary": "#8c4b00",
                        "on-tertiary": "#ffffff",
                        "primary-fixed": "#dbe1ff",
                        "inverse-surface": "#2f3037",
                        "outline": "#747684",
                        "on-tertiary-container": "#fffbff",
                        "on-primary-fixed": "#00174d",
                        "on-secondary-fixed-variant": "#444653",
                        "inverse-primary": "#b5c4ff",
                        "primary-fixed-dim": "#b5c4ff",
                        "surface-container": "#eeedf7",
                        "primary": "#2b54bf",
                        "tertiary-fixed": "#ffdcc2",
                        "background": "#faf8ff",
                        "on-background": "#1a1b22",
                        "secondary-fixed": "#e0e1f2",
                        "on-error-container": "#93000a",
                        "on-secondary": "#ffffff",
                        "on-tertiary-fixed-variant": "#6d3900",
                        "on-primary-container": "#fefcff",
                        "outline-variant": "#c4c6d5"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "container-padding": "1.5rem",
                        "sidebar-width": "224px",
                        "sidebar-collapsed": "104px",
                        "card-spacer": "1.25rem",
                        "gutter": "1.5rem"
                    },
                    "fontFamily": {
                        "headline-md": ["Nunito Sans"],
                        "body-md": ["Nunito Sans"],
                        "label-md": ["Nunito Sans"],
                        "label-lg": ["Nunito Sans"],
                        "headline-xl": ["Nunito Sans"],
                        "body-lg": ["Nunito Sans"],
                        "headline-lg": ["Nunito Sans"]
                    },
                    "fontSize": {
                        "headline-md": ["1.25rem", {"lineHeight": "1.2", "fontWeight": "600"}],
                        "body-md": ["0.875rem", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "label-md": ["0.7rem", {"lineHeight": "1", "fontWeight": "600"}],
                        "label-lg": ["0.75rem", {"lineHeight": "1", "fontWeight": "700"}],
                        "headline-xl": ["1.75rem", {"lineHeight": "1.2", "fontWeight": "700"}],
                        "body-lg": ["1rem", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "headline-lg": ["1.5rem", {"lineHeight": "1.2", "fontWeight": "600"}]
                    }
                },
            },
        }
    </script>

    {{-- Base Styles --}}
    <style>
        body { font-family: 'Nunito Sans', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    {{-- Additional page-level styles --}}
    @stack('styles')
</head>
<body class="bg-surface-container-low text-on-background min-h-screen flex flex-col">

    {{-- ===== HEADER ===== --}}
    @include('layouts_recruitments.partials._header')

    {{-- ===== MAIN CONTENT ===== --}}
    <main class="mt-14 sm:mt-16 flex-grow flex flex-col items-center w-full py-5 sm:py-8 px-3 sm:px-4 md:px-6">

        {{-- Page Title & Breadcrumb --}}
        <div class="w-full max-w-5xl flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-8 gap-2 sm:gap-0">
            <h1 class="font-headline-xl text-[1.3rem] sm:text-headline-xl text-on-surface">
                @yield('page_title', 'Form Pendaftaran')
            </h1>
            <nav class="flex text-label-lg text-on-surface-variant text-[0.7rem] sm:text-xs">
                @yield('breadcrumb')
            </nav>
        </div>

        {{-- Main Page Content --}}
        <div class="w-full max-w-5xl">
            @yield('content')
        </div>

    </main>

    {{-- ===== FOOTER ===== --}}
    @include('layouts_recruitments.partials._footer')

{{-- Base Scripts --}}
@stack('scripts')
</body>
</html>
