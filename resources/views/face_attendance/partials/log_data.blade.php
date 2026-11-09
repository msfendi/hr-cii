@if(($logs ?? collect())->isEmpty())
    <div class="text-center text-muted py-5">
        <i class="fas fa-clipboard-list fa-3x mb-3 text-gray-300"></i>
        <div>Belum ada data log. Klik “Tampilkan” setelah memilih tanggal.</div>
    </div>
@else
<div class="table-responsive">
<table id="tableLogFace" class="table table-bordered table-hover w-100">
    <thead class="thead-primary">
        <tr>
            <th style="min-width:90px">Tanggal</th>
            <th style="min-width:70px">Waktu</th>
            <th style="min-width:160px">Karyawan</th>
            <th style="min-width:110px">NPK / Barcode</th>
            <th style="min-width:120px">Departemen</th>
            <th style="min-width:90px">Mode</th>
            <th style="min-width:90px">Aksi</th>
            <th style="min-width:90px">Verifymode</th>
            <th style="min-width:110px">Status GPS</th>
            <th style="min-width:100px">Foto</th>
            <th style="min-width:90px">Confidence</th>
            <th style="min-width:100px">Status</th>
            <th style="min-width:180px">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->scan_date ? \Carbon\Carbon::parse($log->scan_date)->format('Y-m-d') : '-' }}</td>
                <td>{{ $log->scan_date ? \Carbon\Carbon::parse($log->scan_date)->format('H:i:s') : '-' }}</td>
                <td><strong>{{ $log->name ?? '-' }}</strong>@if($log->is_keluar ?? false)<span class="badge badge-warning ml-1">Keluar</span>@endif</td>
                <td>{{ $log->npk ?? '-' }}<br><small class="text-muted">pin: {{ $log->pin ?? '-' }}</small></td>
                <td>{{ $log->dept ?? '-' }}</td>
                <td>
                    @if(($log->mode_label ?? 'Fingerprint') === 'Wajah')
                        <span class="badge badge-primary">Wajah</span>
                    @else
                        <span class="badge badge-info">Fingerprint</span>
                    @endif
                </td>
                <td>{{ $log->action_label ?? '-' }}</td>
                <td>{{ $log->verifymode ?? '-' }}</td>
                <td>
                    @if($log->inside_polygon !== null && $log->inside_polygon !== '')
                        @if((int)$log->inside_polygon === 1)<span class="badge badge-success">Di dalam</span>@else<span class="badge badge-danger">Di luar</span>@endif
                    @else - @endif
                    @if($log->latitude !== null && $log->longitude !== null)
                        <br><small class="text-muted">{{ number_format((float)$log->latitude, 6) }}, {{ number_format((float)$log->longitude, 6) }}</small>
                    @endif
                </td>
                <td>
                    @if(!empty($log->face_image))
                        <a href="javascript:void(0)" class="btn-preview-face" data-img="{{ $log->face_image }}" data-name="{{ $log->name ?? '-' }}" data-npk="{{ $log->npk ?? '-' }}">
                            <img data-src="{{ str_starts_with($log->face_image, 'data:') ? $log->face_image : ('data:image/jpeg;base64,' . $log->face_image) }}" alt="Foto wajah" class="face-thumb rounded border shadow-sm" style="width:48px;height:48px;object-fit:cover;" loading="lazy">
                        </a>
                    @else <span class="text-muted">-</span> @endif
                </td>
                <td>
                    @if($log->face_match !== null && $log->face_match !== '')
                        @if((int)$log->face_match === 1)<span class="badge badge-success">Match</span>@else<span class="badge badge-danger">Tidak match</span>@endif
                    @endif
                    @if($log->confidence !== null && $log->confidence !== '')
                        <br><span class="small font-weight-bold">{{ number_format((float)$log->confidence, 2) }}%</span>
                    @endif
                </td>
                <td>
                    @if(!empty($log->status))<span class="badge badge-secondary">{{ $log->status }}</span>@else - @endif
                </td>
                <td>
                    @if(!empty($log->rejection_reason)){{ $log->rejection_reason }}@endif
                    @if(!empty($log->ip_address))<br><small class="text-muted">IP: {{ $log->ip_address }}</small>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="13" class="text-center text-muted py-4">Tidak ada log pada rentang tanggal ini.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
@if(($pagination['total_pages'] ?? 0) > 1)
    <nav aria-label="Pagination log"><ul class="pagination justify-content-center">
        @if(($pagination['has_prev'] ?? false))
            <li class="page-item"><a class="page-link" href="#" data-page="{{ $pagination['current_page'] - 1 }}">Sebelumnya</a></li>
        @endif
        <li class="page-item disabled"><span class="page-link">Halaman {{ $pagination['current_page'] }} dari {{ $pagination['total_pages'] }} ({{ $pagination['total'] }} transaksi)</span></li>
        @if(($pagination['has_next'] ?? false))
            <li class="page-item"><a class="page-link" href="#" data-page="{{ $pagination['current_page'] + 1 }}">Berikutnya</a></li>
        @endif
    </ul></nav>
@endif
<div class="modal fade" id="modalFaceLog" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary"><h5 class="modal-title text-white">Foto Wajah</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body text-center"><img id="modalFaceLogImg" src="" alt="Foto wajah" class="img-fluid rounded shadow border" style="max-height:380px;"><div class="mt-2 font-weight-bold" id="modalFaceLogName">-</div><div class="small text-muted" id="modalFaceLogNpk">-</div></div></div></div></div>
<script>
(function(){
    function observe(){ document.querySelectorAll('.face-thumb[data-src]').forEach(function(img){ if(!img.getAttribute('src')){ var io=new IntersectionObserver(function(es){ es.forEach(function(en){ if(en.isIntersecting){ img.setAttribute('src', img.getAttribute('data-src')); io.disconnect(); } }); }); io.observe(img);} }); }
    observe();
    document.querySelectorAll('.btn-preview-face').forEach(function(b){ b.addEventListener('click', function(){ var d=b.getAttribute('data-img'); if(d && !d.startsWith('data:')) d='data:image/jpeg;base64,'+d; document.getElementById('modalFaceLogImg').setAttribute('src', d); document.getElementById('modalFaceLogName').textContent=b.getAttribute('data-name')||'-'; document.getElementById('modalFaceLogNpk').textContent='NPK: '+(b.getAttribute('data-npk')||'-'); var m=document.getElementById('modalFaceLog'); if(window.jQuery && jQuery.fn.modal) jQuery(m).modal('show'); }); });
})();
</script>
@endif
