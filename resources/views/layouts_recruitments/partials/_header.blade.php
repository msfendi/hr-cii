{{--
    Partial: _header.blade.php
    Usage: @include('layouts_recruitments.partials._header')

    Available slots / variables:
    - $authUser  : object dengan property ->name dan ->avatar_url  (opsional, fallback ke Auth::user())
--}}

@php
    $user = $authUser ?? Auth::user();
@endphp

<header class="fixed top-0 right-0 left-0 z-30 h-14 sm:h-16 flex items-center justify-between px-4 sm:px-container-padding
               bg-surface dark:bg-surface-container-highest
               border-b border-outline-variant dark:border-none
               shadow-sm transition-colors duration-150">

    {{-- Brand / Logo --}}
    <div class="flex items-center gap-2 sm:gap-3">
        <span class="material-symbols-outlined text-primary text-[1.3rem] sm:text-[1.4rem]"
              style="font-variation-settings: 'FILL' 1;">person_add</span>
        <div class="flex flex-col leading-tight">
            <span class="text-primary font-bold text-[0.9rem] sm:text-[1rem] tracking-tight">Chutex Recruitment</span>
            <span class="text-on-surface-variant text-[0.6rem] sm:text-[0.7rem] font-medium hidden xs:block">PT Chutex International Indonesia</span>
        </div>
    </div>

    {{-- Right-side Actions --}}
    <div class="flex items-center gap-2 sm:gap-3">
        <div class="flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1 sm:py-1.5 bg-surface-container-low rounded-full border border-outline-variant">
            <span class="material-symbols-outlined text-primary text-[0.9rem] sm:text-[1rem]">assignment</span>
            <span class="text-[0.7rem] sm:text-label-lg text-on-surface-variant font-bold">Step {{ $currentStep ?? 1 }} / 8</span>
        </div>
    </div>
</header>
