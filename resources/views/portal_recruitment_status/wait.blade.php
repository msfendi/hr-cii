<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>RecruitFlow - Menunggu Hasil Seleksi</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&amp;display=swap"
        rel="stylesheet" />
    <!-- Material Symbols -->
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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

<body class="bg-[#faf8ff] text-gray-800 min-h-screen flex flex-col">
    <!-- TopNavBar -->
    @include('layouts_recruitments.partials._header', ['hideStep' => true])
    
    <!-- Main Content Area -->
    <main class="flex-grow pt-24 pb-12 flex items-center justify-center px-4">
        <div class="w-full max-w-lg text-center bg-white p-10 rounded-xl card-shadow border border-gray-200">
            <span class="material-symbols-outlined text-[80px] text-orange-500 mb-4 animate-pulse" data-icon="hourglass_empty">hourglass_empty</span>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Aplikasi Sedang Diproses</h1>
            <p class="text-gray-600 mb-8 text-md leading-relaxed">
                Tim HR kami sedang memproses aplikasi Anda. Harap tunggu dan kembali cek status Anda secara berkala.
            </p>
            
            <a href="{{ route('portal.recruitment-status.index') }}" 
               class="inline-flex items-center justify-center gap-2 bg-[#2b54bf] hover:bg-blue-800 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-colors w-full">
                <span class="material-symbols-outlined text-[20px]" data-icon="arrow_back">arrow_back</span>
                Kembali ke Halaman Cek
            </a>
        </div>
    </main>
    
    <!-- Visual Background Element (Atmospheric) -->
    <div class="fixed bottom-0 left-0 w-full h-1/2 pointer-events-none opacity-20 -z-10">
        <div class="absolute inset-0 bg-gradient-to-t from-blue-100 to-transparent"></div>
    </div>
    
    <!-- Footer Section -->
    @include('layouts_recruitments.partials._footer')
</body>

</html>
