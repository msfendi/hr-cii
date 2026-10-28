{{--
    Field bertipe daftar berulang (riwayat pendidikan, pengalaman kerja, saudara, anak).

    Mengikuti pola form rekrutmen lama: setiap entri = kartu bernomor dengan
    accent kiri, grid 2 kolom di dalamnya, dan tombol tambah full-width.

    Params: $key, $definition, $rows (array), $meta (array|null), $documents, $lowConf, $fieldIcons
--}}
@php
    $items = $definition['items'] ?? [];
    $rows  = is_array($rows) ? $rows : [];
    $listHasError = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, $key . '.'));
    $entryTitle = $definition['label'];
    $fieldIcons = $fieldIcons ?? [];
@endphp

<div class="rf-field {{ $listHasError ? 'has-error' : '' }}" data-field="{{ $key }}">
    <div class="rf-field-head">
        @if ($showTitle ?? true)
            <label class="rf-label">{{ $entryTitle }}</label>
            @include('recruitment_ai.partials._badge', ['key' => $key, 'meta' => $meta, 'lowConf' => $lowConf, 'documents' => $documents])
        @endif
    </div>

    <div class="mt-1" data-list-rows="{{ $key }}">
        @forelse ($rows as $i => $row)
            <div class="rf-entry {{ collect($errors->keys())->contains(fn ($k) => str_starts_with($k, $key . '.' . $i . '.')) ? 'has-error' : '' }}" data-row="{{ $key }}">
                <div class="rf-entry-head">
                    <span class="rf-entry-num">{{ $i + 1 }}</span>
                    <span class="rf-entry-title" data-base="{{ $entryTitle }}">{{ $entryTitle }} ke-{{ $i + 1 }}</span>
                    <button type="button" class="rf-entry-del" data-remove-row title="Hapus entri ini">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <div class="rf-entry-body">
                    <div class="rf-entry-grid">
                        @foreach ($items as $itemKey => $item)
                            @php
                                $itemType = $item['type'] ?? 'text';
                                $itemName = $key . '[' . $i . '][' . $itemKey . ']';
                                $itemVal  = $row[$itemKey] ?? null;
                                $itemId   = 'fld-' . $key . '-' . $i . '-' . $itemKey;
                                $itemErr  = $errors->first($key . '.' . $i . '.' . $itemKey);
                                $itemIcon = $fieldIcons[$key . '.' . $itemKey] ?? ($item['icon'] ?? null);
                                $isWide   = in_array($itemKey, ['perusahaan', 'institusi', 'nama'], true);
                                $isFull   = $itemType === 'textarea';
                            @endphp
                            <div class="{{ $isFull ? 'rf-span-full' : ($isWide ? 'rf-span-2' : '') }}">
                                <label class="rf-label" for="{{ $itemId }}">
                                    {{ $item['label'] }}
                                    @if (! empty($item['required']))<span class="rf-req">*</span>@endif
                                </label>

                                <span class="rf-control">
                                    @if ($itemType === 'boolean')
                                        <label class="rf-check">
                                            <input type="checkbox" id="{{ $itemId }}" name="{{ $itemName }}" value="1"
                                                @checked((bool) $itemVal)>
                                            Ya
                                        </label>

                                    @elseif ($itemType === 'select')
                                        <select class="rf-select {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                            @if ($itemErr) aria-invalid="true" @endif>
                                            <option value="">Pilih...</option>
                                            @foreach ($item['options'] as $option)
                                                <option value="{{ $option }}" @selected((string) $itemVal === (string) $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>

                                    @elseif ($itemType === 'radio')
                                        <div class="rf-radios rf-radios-sm" role="radiogroup" aria-label="{{ $item['label'] }}">
                                            @foreach ($item['options'] as $option)
                                                @php $optId = $itemId . '-' . \Illuminate\Support\Str::slug($option); @endphp
                                                <label class="rf-radio" for="{{ $optId }}">
                                                    <input type="radio" id="{{ $optId }}" name="{{ $itemName }}" value="{{ $option }}"
                                                        @checked((string) $itemVal === (string) $option)>
                                                    <span>{{ $item['labels'][$option] ?? $option }}</span>
                                                </label>
                                            @endforeach
                                        </div>

                                    @elseif ($itemType === 'date')
                                        <input type="date" class="rf-input {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                            value="{{ $itemVal }}" @if ($itemErr) aria-invalid="true" @endif>

                                    @elseif ($itemType === 'month')
                                        @php
                                            $isSampaiDisabled = $itemKey === 'sampai' && ! empty($row['masih_bekerja']);
                                        @endphp
                                        <input type="month" class="rf-input {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                            value="{{ $isSampaiDisabled ? '' : $itemVal }}" @disabled($isSampaiDisabled) @if ($itemErr) aria-invalid="true" @endif>

                                    @elseif ($itemType === 'number')
                                        <input type="number" class="rf-input {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                            value="{{ $itemVal }}" placeholder="{{ $item['label'] }}"
                                            @if ($itemErr) aria-invalid="true" @endif>

                                    @elseif ($itemType === 'textarea')
                                        <textarea class="rf-textarea" id="{{ $itemId }}" name="{{ $itemName }}" rows="2"
                                            placeholder="{{ $item['label'] }}">{{ $itemVal }}</textarea>

                                    @else
                                        <input type="text" class="rf-input {{ $itemErr ? 'is-invalid' : '' }}" id="{{ $itemId }}" name="{{ $itemName }}"
                                            value="{{ $itemVal }}" placeholder="{{ $item['label'] }}"
                                            @if ($itemErr) aria-invalid="true" @endif>
                                    @endif
                                </span>

                                @if ($itemErr)
                                    <p class="rf-error">{{ $itemErr }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <p class="rf-list-empty" data-list-empty="{{ $key }}">
                Belum ada data. Klik tombol di bawah untuk menambah, atau biarkan AI yang mengisinya dari dokumen.
            </p>
        @endforelse
    </div>

    {{-- Tombol tambah selalu ada (judul field boleh disembunyikan, tombolnya jangan) --}}
    <button type="button" class="rf-btn-add mt-2" data-add-list="{{ $key }}">
        <i class="fas fa-plus"></i>
        {{ $definition['add_label'] ?? 'Tambah' }}
    </button>

    @foreach ($errors->get($key) as $listErr)
        <p class="rf-error">{{ $listErr }}</p>
    @endforeach

    <template data-list-template="{{ $key }}">
        <div class="rf-entry" data-row="{{ $key }}">
            <div class="rf-entry-head">
                <span class="rf-entry-num">1</span>
                <span class="rf-entry-title" data-base="{{ $entryTitle }}">{{ $entryTitle }} ke-1</span>
                <button type="button" class="rf-entry-del" data-remove-row title="Hapus entri ini">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="rf-entry-body">
                <div class="rf-entry-grid">
                    @foreach ($items as $itemKey => $item)
                        @php
                            $itemType = $item['type'] ?? 'text';
                            $itemName = $key . '[__INDEX__][' . $itemKey . ']';
                            $itemId   = 'fld-' . $key . '-__INDEX__-' . $itemKey;
                            $itemIcon = $fieldIcons[$key . '.' . $itemKey] ?? ($item['icon'] ?? null);
                            $isWide   = in_array($itemKey, ['perusahaan', 'institusi', 'nama'], true) || $itemType === 'textarea';
                        @endphp
                        <div class="{{ $itemType === 'textarea' ? 'rf-span-full' : ($isWide ? 'rf-span-2' : '') }}">
                            <label class="rf-label" for="{{ $itemId }}">
                                {{ $item['label'] }}
                                @if (! empty($item['required']))<span class="rf-req">*</span>@endif
                            </label>

                            <span class="rf-control">
                                @if ($itemType === 'boolean')
                                    <label class="rf-check">
                                        <input type="checkbox" id="{{ $itemId }}" name="{{ $itemName }}" value="1">
                                        Ya
                                    </label>

                                @elseif ($itemType === 'select')
                                    <select class="rf-select" id="{{ $itemId }}" name="{{ $itemName }}">
                                        <option value="">Pilih...</option>
                                        @foreach ($item['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>

                                @elseif ($itemType === 'radio')
                                    <div class="rf-radios rf-radios-sm" role="radiogroup" aria-label="{{ $item['label'] }}">
                                        @foreach ($item['options'] as $option)
                                            @php $optId = $itemId . '-' . \Illuminate\Support\Str::slug($option); @endphp
                                            <label class="rf-radio" for="{{ $optId }}">
                                                <input type="radio" id="{{ $optId }}" name="{{ $itemName }}" value="{{ $option }}">
                                                <span>{{ $item['labels'][$option] ?? $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                @elseif ($itemType === 'date')
                                    <input type="date" class="rf-input" id="{{ $itemId }}" name="{{ $itemName }}">

                                @elseif ($itemType === 'month')
                                    <input type="month" class="rf-input" id="{{ $itemId }}" name="{{ $itemName }}">

                                @elseif ($itemType === 'number')
                                    <input type="number" class="rf-input" id="{{ $itemId }}" name="{{ $itemName }}">

                                @elseif ($itemType === 'textarea')
                                    <textarea class="rf-textarea" id="{{ $itemId }}" name="{{ $itemName }}" rows="2"></textarea>

                                @else
                                    <input type="text" class="rf-input" id="{{ $itemId }}" name="{{ $itemName }}" placeholder="{{ $item['label'] }}">
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>
