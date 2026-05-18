{{--
    Partial: _wizard_card.blade.php
    Usage: @include('layouts_recruitments.partials._wizard_card', ['currentStep' => 1, 'totalSteps' => 10])

    Gunakan partial ini untuk membungkus konten form pada setiap step.
    Slot konten disediakan via @yield atau $slot tergantung pola yang dipakai.

    Variables:
    - $currentStep  : int — step aktif saat ini
    - $totalSteps   : int — total jumlah step
    - $formAction   : string — URL action form (default '#')
    - $formMethod   : string — method HTTP form (default 'POST')

    Letakkan konten form antara @include ini dengan menggunakan
    pola @section('wizard_body') di view yang extend layout utama,
    atau teruskan $slot bila menggunakan Blade Components.
--}}

@php
    $formAction  = $formAction  ?? '#';
    $formMethod  = strtoupper($formMethod ?? 'POST');
    $isFirstStep = $currentStep === 1;
    $isLastStep  = $currentStep === $totalSteps;
@endphp

<div class="bg-surface-container-lowest rounded-xl
            shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
            border-l-4 border-primary
            overflow-hidden mb-8">

    <form id="registrationForm"
          action="{{ $formAction }}"
          method="{{ $formMethod === 'GET' ? 'GET' : 'POST' }}"
          enctype="multipart/form-data"
          class="flex flex-col min-h-[500px]">

        @csrf
        @if(!in_array($formMethod, ['GET', 'POST']))
            @method($formMethod)
        @endif

        {{-- ===== FORM BODY — diisi oleh masing-masing step view ===== --}}
        <div class="flex-grow p-8">
            @yield('wizard_body')
        </div>

        {{-- ===== NAVIGATION FOOTER ===== --}}
        <div class="flex items-center justify-between px-8 py-5
                    border-t border-outline-variant bg-surface-container-low">

            {{-- Prev Button --}}
            @if(!$isFirstStep)
                <a href="{{ route(request()->route()->getName(), ['step' => $currentStep - 1]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined text-[1rem]">arrow_back</span>
                    Previous
                </a>
            @else
                <div></div> {{-- spacer --}}
            @endif

            {{-- Step Counter --}}
            <span class="text-label-lg text-on-surface-variant">
                Step {{ $currentStep }} of {{ $totalSteps }}
            </span>

            {{-- Next / Submit Button --}}
            @if(!$isLastStep)
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                               bg-primary text-white text-label-lg font-bold
                               hover:bg-primary/90 transition-colors duration-200 shadow-sm">
                    Next
                    <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
                </button>
            @else
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                               bg-primary text-white text-label-lg font-bold
                               hover:bg-primary/90 transition-colors duration-200 shadow-sm">
                    <span class="material-symbols-outlined text-[1rem]">check_circle</span>
                    Submit
                </button>
            @endif

        </div>
    </form>
</div>
