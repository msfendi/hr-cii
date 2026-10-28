{{--
    Chip status hasil ekstraksi AI untuk satu field.
    Params: $key, $meta (array|null), $lowConf (float), $documents (array)
--}}
@php
    $conf     = $meta ? (float) ($meta['confidence'] ?? 0) : null;
    $srcCode  = $meta['source'] ?? null;
    $srcLabel = $srcCode ? ($documents[$srcCode]['short'] ?? ($documents[$srcCode]['label'] ?? $srcCode)) : null;
    $srcFull  = $srcCode ? ($documents[$srcCode]['label'] ?? $srcCode) : null;
    $conflict = ! empty($meta['conflict']);
    $low      = $conf !== null && $conf < $lowConf;
    $tone     = $conflict ? 'conflict' : ($low ? 'low' : 'ok');
    $text     = $conflict ? 'Konflik, cek manual' : 'AI · ' . $srcLabel . ' · ' . (int) round($conf * 100) . '%';
@endphp

<div class="rf-ai-wrap" data-badge-wrap="{{ $key }}">
    @if ($meta)
        <span class="rf-ai-chip {{ $tone }}"
            title="{{ $conflict ? 'Ditemukan lebih dari satu nilai berbeda, pilih yang benar' : ('Sumber: ' . $srcFull . ' · keyakinan ' . (int) round($conf * 100) . '%' . (! empty($meta['note']) ? ' · ' . $meta['note'] : '')) }}">
            <i class="fas fa-magic"></i>
            {{ $conflict ? 'Konflik' : ($srcLabel . ' · ' . (int) round($conf * 100) . '%') }}
        </span>
    @endif
</div>
