@extends('layouts.app')

@section('title', 'Detail Dokumen - ' . ($document->booking_number ?? $document->original_filename))

@section('content')
    <p><a href="{{ route('pdf-documents.index') }}">&larr; Kembali ke daftar</a></p>

    <h1>{{ $document->booking_number ?? $document->original_filename }}</h1>
    <p class="subtitle">{{ $document->original_filename }} &middot; diupload {{ $document->created_at->format('d M Y, H:i') }}</p>

    <div class="card">
        <h2>Cari Key</h2>
        <input
            type="text"
            id="searchKey"
            placeholder="Ketik key/label, contoh: Vessel/Voy, ETD, Consignee..."
            autocomplete="off"
        >

        <div id="quickResult" style="margin-top:14px; display:none;">
            <div style="font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.03em;">Hasil</div>
            <div id="quickResultLabel" style="font-size:14px; color:var(--muted); margin-top:2px;"></div>
            <div id="quickResultValue" style="font-size:20px; font-weight:600; margin-top:2px;"></div>
        </div>
    </div>

    <div class="card">
        <h2>Semua Field Hasil Ekstraksi</h2>

        <table id="fieldsTable">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Key</th>
                    <th>Label (di PDF)</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($document->fields as $field)
                    <tr
                        data-key="{{ strtolower($field->field_key) }}"
                        data-label="{{ strtolower($field->field_label) }}"
                        data-value="{{ strtolower($field->field_value) }}"
                    >
                        <td><span class="badge">{{ $field->category }}</span></td>
                        <td><code>{{ $field->field_key }}</code></td>
                        <td>{{ $field->field_label }}</td>
                        <td>{{ $field->field_value }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Tidak ada field yang berhasil diparse dari PDF ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <p id="noMatch" class="empty" style="display:none;">Tidak ada field yang cocok dengan pencarian.</p>
    </div>

    <script>
        const searchInput   = document.getElementById('searchKey');
        const rows          = Array.from(document.querySelectorAll('#fieldsTable tbody tr[data-key]'));
        const noMatch       = document.getElementById('noMatch');
        const quickResult   = document.getElementById('quickResult');
        const quickLabelEl  = document.getElementById('quickResultLabel');
        const quickValueEl  = document.getElementById('quickResultValue');

        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visibleCount = 0;
            let firstMatch = null;

            rows.forEach(row => {
                const matches = q === '' ||
                    row.dataset.key.includes(q) ||
                    row.dataset.label.includes(q) ||
                    row.dataset.value.includes(q);

                row.style.display = matches ? '' : 'none';

                if (matches) {
                    visibleCount++;
                    if (!firstMatch) firstMatch = row;
                }
            });

            noMatch.style.display = (visibleCount === 0 && q !== '') ? 'block' : 'none';

            if (q !== '' && firstMatch) {
                quickResult.style.display = 'block';
                quickLabelEl.textContent = firstMatch.children[2].textContent;
                quickValueEl.textContent = firstMatch.children[3].textContent;
            } else {
                quickResult.style.display = 'none';
            }
        });
    </script>
@endsection
