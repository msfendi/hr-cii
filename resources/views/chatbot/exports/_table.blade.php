{{-- Fragmen tabel hasil query AI. Dipakai oleh export HTML dan PDF. --}}
<table>
    <thead>
        <tr>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($headers as $header)
                    <td>{{ data_get($row, $header) }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="kosong" colspan="{{ max(count($headers), 1) }}">Tidak ada data.</td>
            </tr>
        @endforelse
    </tbody>
</table>
