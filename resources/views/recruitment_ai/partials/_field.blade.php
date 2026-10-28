{{--
    Render satu field form rekrutmen (versi AI).

    Params:
      $key        nama field (kunci registry)
      $definition definisi field dari RecruitmentAiFieldRegistry
      $draft      array draft session
      $draftMeta  array meta hasil ekstraksi
      $documents  array daftar dokumen
      $lowConf    float ambang confidence
      $fieldIcons array ikon per field
--}}
@php
    $meta     = $draftMeta[$key] ?? null;
    $rawValue = old($key, $draft['fields'][$key] ?? $definition['default'] ?? null);
    $isList   = ($definition['type'] ?? '') === 'list';
    $isGroup  = ($definition['type'] ?? '') === 'group';
    $type     = $definition['type'] ?? 'text';
    $hasError = $errors->has($key) || ($isGroup && collect($errors->keys())->contains(fn ($k) => str_starts_with($k, $key . '.')));
@endphp

<div class="rf-field {{ $hasError ? 'has-error' : '' }}" data-field="{{ $key }}">
    @if ($isGroup)
        {{-- ── Grup (ayah / ibu) ──
             Kalau section-nya cuma berisi 1 field, judul grup disembunyikan
             (nama section sudah cukup jelas) dan boxnya tidak dipakai lagi,
             supaya tidak terlihat seperti kotak di dalam kotak. --}}
        <div class="{{ ($showTitle ?? true) ? 'rf-sub' : '' }}">
            <div class="{{ ($showTitle ?? true) ? 'rf-sub-title' : 'rf-field-head' }}">
                @if ($showTitle ?? true)
                    {{ $definition['label'] }}
                @endif
                @include('recruitment_ai.partials._badge', ['key' => $key, 'meta' => $meta, 'lowConf' => $lowConf, 'documents' => $documents])
            </div>

            <div class="rf-group-cols">
                @foreach ($definition['items'] as $itemKey => $item)
                    @php
                        $itemValue = old($key . '.' . $itemKey, $rawValue[$itemKey] ?? null);
                        $itemName  = $key . '.' . $itemKey;
                        $itemId    = 'fld-' . $key . '-' . $itemKey;
                        $itemErr   = $errors->first($itemName);
                    @endphp
                    <div class="rf-group-col">
                        <label class="rf-label" for="{{ $itemId }}">
                            {{ $item['label'] }}
                            @if (! empty($item['required']))<span class="rf-req">*</span>@endif
                        </label>

                        @if (($item['type'] ?? 'text') === 'select')
                            <select class="rf-select {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                @if ($itemErr) aria-invalid="true" @endif>
                                <option value="">Pilih {{ $item['label'] }}</option>
                                @foreach ($item['options'] ?? [] as $opt)
                                    <option value="{{ $opt }}" @selected((string) $itemValue === (string) $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif (($item['type'] ?? 'text') === 'date')
                            <input type="date"
                                class="rf-input {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                value="{{ $itemValue }}" placeholder="{{ $item['label'] }}"
                                @if ($itemErr) aria-invalid="true" @endif>
                        @else
                            <input type="text"
                                class="rf-input {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                value="{{ $itemValue }}" placeholder="{{ $item['label'] }}"
                                @if ($itemErr) aria-invalid="true" @endif>
                        @endif

                        <div class="rf-conflict-wrap" data-conflict-wrap="{{ $key . '.' . $itemKey }}"></div>

                        @if ($itemErr)
                            <p class="rf-error"><i class="fas fa-circle-exclamation me-1"></i> {{ $itemErr }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="rf-conflict-wrap" data-conflict-wrap="{{ $key }}">
                @if ($meta && ! empty($meta['conflict']) && ! empty($meta['alternatives']))
                    <div class="rf-ai-alt">
                        <b><i class="fas fa-exclamation-triangle me-1"></i> Nilai lain yang ditemukan:</b>
                        @foreach ($meta['alternatives'] as $alt)
                            <span class="rf-ai-alt-item">
                                <b>{{ is_array($alt['value']) ? json_encode($alt['value']) : $alt['value'] }}</b>
                                &mdash; dari {{ $documents[$alt['source'] ?? '']['label'] ?? ($alt['source'] ?? '-') }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    @elseif ($isList)
        {{-- ── Daftar berulang ── --}}
        @include('recruitment_ai.partials._list', [
            'key'        => $key,
            'definition' => $definition,
            'rows'       => $rawValue ?: [],
            'meta'       => $meta,
            'fieldIcons' => $fieldIcons,
            'showTitle'  => $showTitle ?? true,
        ])

    @else
        {{-- ── Field biasa ── --}}
        <div class="rf-field-head">
            @if ($type === 'radio')
                <span class="rf-label">
                    {{ $definition['label'] }}
                    @if (! empty($definition['required']))<span class="rf-req">*</span>@endif
                </span>
            @else
                <label class="rf-label" for="fld-{{ $key }}">
                    {{ $definition['label'] }}
                    @if (! empty($definition['required']))<span class="rf-req">*</span>@endif
                </label>
            @endif
            @include('recruitment_ai.partials._badge', ['key' => $key, 'meta' => $meta, 'lowConf' => $lowConf, 'documents' => $documents])
        </div>

        @if ($type === 'textarea')
            <textarea class="rf-textarea" id="fld-{{ $key }}" name="{{ $key }}" rows="3"
                placeholder="{{ $definition['placeholder'] ?? $definition['label'] }}">{{ $rawValue }}</textarea>

        @elseif ($type === 'radio')
            <div class="rf-radios" role="radiogroup" aria-label="{{ $definition['label'] }}">
                @foreach ($definition['options'] as $option)
                    @php $optionId = 'fld-' . $key . '-' . \Illuminate\Support\Str::slug($option); @endphp
                    <label class="rf-radio" for="{{ $optionId }}">
                        <input type="radio" id="{{ $optionId }}" name="{{ $key }}" value="{{ $option }}"
                            @checked((string) $rawValue === (string) $option)>
                        <span>{{ $definition['labels'][$option] ?? $option }}</span>
                    </label>
                @endforeach
            </div>

        @elseif ($type === 'select')
            <select class="rf-select {{ $errors->has($key) ? 'is-invalid' : '' }}" id="fld-{{ $key }}" name="{{ $key }}"
                @if ($errors->has($key)) aria-invalid="true" @endif>
                <option value="">Pilih...</option>
                @php
                    $knownOptions = array_map('strval', $definition['options']);
                    $isKnown = $rawValue === null || $rawValue === '' || in_array((string) $rawValue, $knownOptions, true);
                @endphp
                @if (! $isKnown)
                    <option value="{{ $rawValue }}" selected>{{ $rawValue }}</option>
                @endif
                @foreach ($definition['options'] as $option)
                    <option value="{{ $option }}" @selected((string) $rawValue === (string) $option)>{{ $definition['labels'][$option] ?? $option }}</option>
                @endforeach
            </select>

        @elseif ($type === 'number')
            <input type="number" class="rf-input {{ $errors->has($key) ? 'is-invalid' : '' }}" id="fld-{{ $key }}" name="{{ $key }}"
                value="{{ $rawValue }}" placeholder="{{ $definition['placeholder'] ?? $definition['label'] }}"
                @if ($errors->has($key)) aria-invalid="true" @endif>

        @elseif ($type === 'date')
            @php
                $maxDate = $key === 'tanggal_lahir' ? now()->subYears(18)->toDateString() : ($definition['max'] ?? null);
            @endphp
            <input type="date" class="rf-input {{ $errors->has($key) ? 'is-invalid' : '' }}" id="fld-{{ $key }}" name="{{ $key }}"
                value="{{ $rawValue }}"
                @if ($maxDate) max="{{ $maxDate }}" @endif
                @if ($errors->has($key)) aria-invalid="true" @endif>

        @else
            <input type="text" class="rf-input {{ $errors->has($key) ? 'is-invalid' : '' }}" id="fld-{{ $key }}" name="{{ $key }}"
                value="{{ $rawValue }}" placeholder="{{ $definition['placeholder'] ?? $definition['label'] }}"
                @if ($errors->has($key)) aria-invalid="true" @endif>
        @endif

        {{-- Pesan konflik AI ditaruh rapi di bawah input field --}}
        <div class="rf-conflict-wrap" data-conflict-wrap="{{ $key }}">
            @if ($meta && ! empty($meta['conflict']) && ! empty($meta['alternatives']))
                <div class="rf-ai-alt">
                    <b><i class="fas fa-exclamation-triangle me-1"></i> Nilai lain yang ditemukan:</b>
                    @foreach ($meta['alternatives'] as $alt)
                        <span class="rf-ai-alt-item">
                            <b>{{ is_array($alt['value']) ? json_encode($alt['value']) : $alt['value'] }}</b>
                            &mdash; dari {{ $documents[$alt['source'] ?? '']['label'] ?? ($alt['source'] ?? '-') }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        @error($key)
            <p class="rf-error"><i class="fas fa-circle-exclamation me-1"></i> {{ $message }}</p>
        @enderror
    @endif
</div>
