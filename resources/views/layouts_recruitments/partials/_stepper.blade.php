{{--
    Partial: _stepper.blade.php
    Usage: @include('layouts_recruitments_.partials._stepper', ['currentStep' => 1, 'steps' => $steps])

    Variables:
    - $currentStep  : int  — step yang sedang aktif (1-based)
    - $steps        : array — daftar label step, contoh:
                      ['Personal', 'Address & Education', 'Physical', 'Documents', ...]
--}}

@php
    $totalSteps = count($steps);
    $progressPercent = $totalSteps > 1
        ? round((($currentStep - 1) / ($totalSteps - 1)) * 100)
        : 0;
@endphp

<div class="mb-10 px-4" id="stepper-container">
    <div class="flex items-center justify-between relative">

        {{-- Progress Bar Background --}}
        <div class="absolute top-1/2 left-0 w-full h-0.5 bg-outline-variant -translate-y-1/2 z-0"></div>

        {{-- Progress Bar Fill --}}
        <div class="absolute top-1/2 left-0 h-0.5 bg-primary -translate-y-1/2 z-0 transition-all duration-500"
             style="width: {{ $progressPercent }}%"
             id="step-progress"></div>

        {{-- Step Items --}}
        @foreach($steps as $index => $label)
            @php
                $stepNumber  = $index + 1;
                $isActive    = $stepNumber === $currentStep;
                $isCompleted = $stepNumber < $currentStep;

                $stateClass  = $isActive    ? 'active'
                             : ($isCompleted ? 'completed' : '');

                $circleBase  = "step-number w-10 h-10 rounded-full border-2 flex items-center justify-center transition-all duration-300 font-bold";
                
                if ($isActive) {
                    $circleClass = "$circleBase bg-primary border-primary text-white shadow-[0_0_0_4px_#dbe1ff]";
                } elseif ($isCompleted) {
                    $circleClass = "$circleBase bg-primary border-primary text-white";
                } else {
                    $circleClass = "$circleBase bg-surface border-outline-variant text-on-surface-variant";
                }
            @endphp

            <div class="step-item {{ $stateClass }} z-10 flex flex-col items-center gap-2 group"
                 data-step="{{ $stepNumber }}">

                {{-- Circle --}}
                <div class="{{ $circleClass }}">
                    @if($isCompleted)
                        {{-- Checkmark --}}
                        <span class="material-symbols-outlined text-[1.1rem]">check</span>
                    @else
                        <span>{{ $stepNumber }}</span>
                    @endif
                </div>

                {{-- Label --}}
                <!-- <span class="step-label text-label-lg text-center transition-all duration-300
                             {{ $isActive ? 'text-primary font-bold' : 'text-on-surface-variant' }}">
                    {{ $label }}
                </span> -->
            </div>
        @endforeach

    </div>
</div>
