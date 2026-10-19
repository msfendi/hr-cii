<!DOCTYPE html>
<html lang="en">
@include('layout.header')
@include('sweetalert::alert')
<body id="page-top" class="rekon-summary-page">

<div id="wrapper">

    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            @include('layout.navbar')

            <div class="container-fluid" id="sum-app"
                 data-data-url="{{ route('monitoring.rekonsiliasi.summary.data') }}"
                 data-template-url="{{ route('monitoring.rekonsiliasi.summary.grade-template') }}"
                 data-import-url="{{ route('monitoring.rekonsiliasi.summary.grade-import') }}"
                 data-notes-template-url="{{ route('monitoring.rekonsiliasi.summary.notes-template') }}"
                 data-notes-import-url="{{ route('monitoring.rekonsiliasi.summary.notes-import') }}"
                 data-status-url="{{ route('monitoring.rekonsiliasi.summary.update-status') }}"
                 data-lock-types='@json(\App\Models\MonRekonLock::types())'
                 data-mode="{{ $mode ?? 'ocf' }}">

                {{-- ================= HEADER ================= --}}
                <div class="sum-hero shadow mb-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:12px">
                        <div>
                            <div class="sum-hero-title"><i class="fas fa-tshirt mr-2"></i>SHIPMENT RECONCILIATION &ndash; SUMMARY</div>
                            <div class="sum-hero-sub">Production Audit &nbsp;|&nbsp; Efficiency &nbsp;|&nbsp; Profitability</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center" style="gap:8px">
                            <div class="btn-group btn-group-sm" role="group" id="sum-mode" aria-label="Summary mode">
                                <button type="button" class="btn btn-outline-light" data-mode="ocf">By OCF</button>
                                <button type="button" class="btn btn-outline-light" data-mode="cpo">By CPO</button>
                            </div>
                            <div class="d-flex align-items-center" style="gap:6px">
                                <label class="text-white small mb-0">Lock Period</label>
                                <input type="date" id="sum-from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                                <span class="text-white">to</span>
                                <input type="date" id="sum-to" class="form-control form-control-sm" value="{{ $dateTo }}">
                                <button type="button" id="sum-apply" class="btn btn-light btn-sm"><i class="fas fa-filter"></i> Apply</button>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-download fa-sm"></i> Template
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="#" id="sum-template">Grade A / B / C</a>
                                    <a class="dropdown-item" href="#" id="sum-notes-template">Recommendations &amp; Notes</a>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-file-import fa-sm"></i> Import
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="#" id="sum-import">Grade A / B / C</a>
                                    <a class="dropdown-item" href="#" id="sum-notes-import">Recommendations &amp; Notes</a>
                                </div>
                            </div>
                            <input type="file" id="sum-file" accept=".xlsx,.xls" style="display:none">
                            <input type="file" id="sum-notes-file" accept=".xlsx,.xls" style="display:none">
                            <a href="javascript:history.back()" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left fa-sm"></i> Back</a>
                        </div>
                    </div>
                    <div class="sum-hero-sub mt-2" id="sum-period-label">-</div>
                </div>

                <div id="sum-empty" class="card shadow mb-4" style="display:none">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-lock fa-2x mb-3 d-block"></i>
                        <div class="font-weight-bold mb-1">No <span class="sum-mode-lbl">OCF</span> has been locked in this period</div>
                        <div class="small">Use the <strong>Lock Data</strong> button (mode <strong class="sum-mode-lbl">OCF</strong>) on the Reconciliation Dashboard, then select the lock period above.</div>
                    </div>
                </div>

                <div id="sum-body" style="display:none">

                    {{-- ================= TOP 3 ================= --}}
                    <div class="row">
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 sum-card">
                                <div class="sum-card-head bg-danger"><i class="fas fa-exclamation-triangle mr-1"></i> TOP 3 &ndash; SHORT SHIPMENT (% (CUTTING &minus; SHIPMENT) / CUTTING)</div>
                                <div class="card-body p-2" id="sum-top3-short_ship"></div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 sum-card">
                                <div class="sum-card-head" style="background:#f97316"><i class="fas fa-exclamation-triangle mr-1"></i> TOP 3 &ndash; PRODUCTION LOSS (% LOSS / CONTRACT)</div>
                                <div class="card-body p-2" id="sum-top3-loss"></div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 sum-card">
                                <div class="sum-card-head bg-primary"><i class="fas fa-layer-group mr-1"></i> TOP 3 &ndash; FABRIC STOCK (% STOCK / RECEIVED)</div>
                                <div class="card-body p-2" id="sum-top3-sisa_fabric"></div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= DETAIL TOP 5 + REKOMENDASI & NOTES ================= --}}
                    <div class="row">
                        <div class="col-xl-8 mb-4">
                            <div class="card shadow sum-card mb-4">
                                <div class="sum-card-head" style="background:#1f3864"><i class="fas fa-list mr-1"></i> TOP 5 <span class="sum-mode-lbl">OCF</span> WITH THE BIGGEST ISSUES &ndash; DETAIL</div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0 sum-table" id="sum-top-impact">
                                            <thead>
                                                <tr>
                                                    <th>No</th><th class="sum-mode-lbl">OCF</th><th>Buyer</th>
                                                    <th class="text-right">Panel Reject (pcs)</th>
                                                    <th class="text-right">Grade A (pcs)</th>
                                                    <th class="text-right">Grade B (pcs)</th>
                                                    <th class="text-right">Grade C (pcs)</th>
                                                    <th class="text-right">Remaining Fabric (kg)</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 mb-4">
                            <div class="card shadow sum-card h-100">
                                <div class="sum-card-head" style="background:#1f3864"><i class="fas fa-bullseye mr-1"></i> RECOMMENDATIONS &amp; NOTES</div>
                                <div class="card-body p-2" id="sum-notes"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

@include('layout.footer')

    </div>
</div>

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .sum-hero { background:#0b3a6e; color:#fff; border-radius:.5rem; padding:16px 20px; }
    .sum-hero-title { font-size:1.35rem; font-weight:800; letter-spacing:.3px; }
    .sum-hero-sub { font-size:.85rem; opacity:.85; }
    .sum-hero input.form-control { width:auto; }
    .sum-section-title { background:#0b3a6e; color:#fff; font-weight:700; padding:6px 14px; border-radius:.35rem; margin-bottom:12px; font-size:.9rem; }
    .sum-card { border:0; overflow:hidden; }
    .sum-card-head { color:#fff; font-weight:700; font-size:.78rem; padding:8px 12px; }
    .sum-kpi { border-radius:.5rem; border:1px solid; padding:12px 14px; height:100%; background:#fff; }
    .sum-kpi .lbl { font-size:.72rem; font-weight:800; text-transform:uppercase; }
    .sum-kpi .val { font-size:1.8rem; font-weight:800; line-height:1.1; }
    .sum-kpi .unit { font-size:.8rem; color:#6b7280; margin-left:4px; }
    .sum-kpi .delta { font-size:.78rem; font-weight:700; }
    .sum-kpi .sub { font-size:.72rem; color:#6b7280; }
    .sum-up-bad, .sum-down-good { color:#dc2626; } /* dipakai lewat kelas dinamis di JS */
    .sum-bad { color:#dc2626; } .sum-good { color:#16a34a; } .sum-neutral { color:#6b7280; }
    .sum-rank { display:flex; align-items:center; gap:8px; padding:6px 4px; border-bottom:1px solid #eef0f4; font-size:.8rem; }
    .sum-rank:last-child { border-bottom:0; }
    .sum-rank .no { width:16px; font-weight:700; }
    .sum-rank .ocf { width:78px; font-weight:600; }
    .sum-rank .buyer { width:50px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sum-bar-wrap { flex:1; background:#f1f3f7; border-radius:3px; height:14px; position:relative; }
    .sum-bar { height:100%; border-radius:3px; }
    .sum-rank .num { width:52px; text-align:right; font-weight:700; }
    .sum-table th { background:#f3f6fb; font-size:.74rem; white-space:nowrap; }
    .sum-table td { font-size:.8rem; vertical-align:middle; }
    .sum-badge { font-size:.7rem; padding:3px 8px; border-radius:10px; font-weight:700; }
    .sum-badge.partial { background:#fef3c7; color:#b45309; }
    .sum-badge.shortship { background:#fee2e2; color:#b91c1c; }
    .sum-badge.complete { background:#dcfce7; color:#15803d; }
    .sum-status-edit { color:#6b7280; margin-left:6px; cursor:pointer; }
    .sum-status-edit:hover { color:#0b3a6e; }
    .sum-badge.Investigasi { background:#fee2e2; color:#b91c1c; }
    .sum-badge.Monitor { background:#fef3c7; color:#b45309; }
    .sum-badge.Follow-up { background:#dcfce7; color:#15803d; }
    .sum-reco-item { display:flex; gap:10px; padding:8px 4px; border-bottom:1px solid #eef0f4; }
    .sum-reco-item:last-child { border-bottom:0; }
    .sum-reco-no { width:26px; height:26px; flex:0 0 26px; border-radius:50%; color:#fff; font-weight:700; display:flex; align-items:center; justify-content:center; }
    .sum-reco ul { padding-left:16px; margin:2px 0 0; font-size:.72rem; color:#4b5563; }
    .sum-footer { background:#e8f1fb; border-radius:.5rem; padding:12px 18px; display:flex; flex-wrap:wrap; gap:24px; align-items:center; font-size:.85rem; }
</style>

<script>
(function () {
    const app = document.getElementById('sum-app');
    const dataUrl = app.dataset.dataUrl;
    const templateUrl = app.dataset.templateUrl;
    const importUrl = app.dataset.importUrl;
    const notesTemplateUrl = app.dataset.notesTemplateUrl;
    const notesImportUrl = app.dataset.notesImportUrl;
    const statusUrl = app.dataset.statusUrl;
    const lockTypes = JSON.parse(app.dataset.lockTypes || '[]');
    let mode = (new URLSearchParams(location.search).get('mode') || app.dataset.mode || 'ocf') === 'cpo' ? 'cpo' : 'ocf';
    const modeLbl = () => (mode === 'cpo' ? 'CPO' : 'OCF');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const fromEl = document.getElementById('sum-from');
    const toEl = document.getElementById('sum-to');

    const fmt = (v, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(Number(v || 0));
    const rp = (v) => 'Rp ' + fmt(v);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const qs = () => new URLSearchParams({ date_from: fromEl.value, date_to: toEl.value, mode }).toString();

    function applyMode() {
        document.querySelectorAll('.sum-mode-lbl').forEach(el => { el.textContent = modeLbl(); });
        document.querySelectorAll('#sum-mode [data-mode]').forEach(b => {
            const on = b.dataset.mode === mode;
            b.classList.toggle('btn-light', on);
            b.classList.toggle('btn-outline-light', !on);
        });
        const u = new URL(location.href);
        u.searchParams.set('mode', mode);
        history.replaceState(null, '', u);
    }

    // usePct: tampilkan % (ship_pct = (output cutting - shipment) / output cutting) dari service, bukan qty.
    // usePct: 'ship' => i.ship_pct (short ship), 'pct' => i.pct (loss / fabric stock).
    function rankList(items, color, usePct = false, unit = 'pcs', lbl = 'Short ship') {
        if (!items.length) return '<div class="text-muted small text-center py-3">No data</div>';
        if (usePct) {
            const pv = (i) => usePct === 'pct' ? i.pct : i.ship_pct;
            const fmtP = (v) => v === null || v === undefined ? '-' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(v) + '%';
            return items.map((i, idx) => `<div class="sum-rank">
                <span class="no">${idx + 1}</span><span class="ocf">${esc(i.ocf)}</span><span class="buyer" title="${esc(i.buyer)}">${esc(i.buyer || '-')}</span>
                <div class="sum-bar-wrap"><div class="sum-bar" style="width:${Math.min(100, Math.abs(pv(i) || 0)).toFixed(1)}%;background:${color}"></div></div>
                <span class="num" title="${lbl}: ${fmt(i.value, 2)} ${unit}">${fmtP(pv(i))}</span></div>`).join('');
        }
        const max = Math.max(...items.map(i => i.value), 1);
        return items.map((i, idx) => `<div class="sum-rank">
            <span class="no">${idx + 1}</span><span class="ocf">${esc(i.ocf)}</span><span class="buyer" title="${esc(i.buyer)}">${esc(i.buyer || '-')}</span>
            <div class="sum-bar-wrap"><div class="sum-bar" style="width:${(i.value / max * 100).toFixed(1)}%;background:${color}"></div></div>
            <span class="num">${fmt(i.value)}</span></div>`).join('');
    }

    function renderTop3(d) {
        document.getElementById('sum-top3-short_ship').innerHTML = rankList(d.top3.short_ship, '#ef4444', 'ship', 'pcs', 'Short ship');
        document.getElementById('sum-top3-loss').innerHTML = rankList(d.top3.loss, '#f97316', 'pct', 'pcs', 'Loss');
        document.getElementById('sum-top3-sisa_fabric').innerHTML = rankList(d.top3.sisa_fabric, '#3b82f6', 'pct', 'kg', 'Fabric stock');
    }

    const label = (v) => String(v || '').charAt(0).toUpperCase() + String(v || '').slice(1);

    function renderTable(rows) {
        document.querySelector('#sum-top-impact tbody').innerHTML = rows.length ? rows.map(r => `<tr>
            <td>${r.no}</td><td><strong>${esc(r.ocf)}</strong>${mode === 'cpo' && r.ocf_numbers ? `<div class="small text-muted" style="max-width:220px;white-space:normal">OCF: ${esc(r.ocf_numbers)}</div>` : ''}</td><td>${esc(r.buyer || '-')}</td>
            <td class="text-right text-danger">${fmt(r.panel_reject)}</td><td class="text-right">${fmt(r.grade_a)}</td><td class="text-right">${fmt(r.grade_b)}</td>
            <td class="text-right">${fmt(r.grade_c)}</td><td class="text-right">${fmt(r.sisa_fabric)}</td>
            <td class="text-nowrap">${r.status ? `<span class="sum-badge ${esc(r.status)}">${esc(label(r.status))}</span>` : '<span class="text-muted">-</span>'}
                <a href="#" class="sum-status-edit" title="Change status" data-lock-id="${esc(r.lock_id)}" data-ocf="${esc(r.ocf)}" data-status="${esc(r.status)}"><i class="fas fa-edit"></i></a></td></tr>`).join('')
            : '<tr><td colspan="9" class="text-center text-muted">No data</td></tr>';
    }

    function renderNotes(list) {
        const colors = { 'Investigasi': '#dc2626', 'Monitor': '#f59e0b', 'Follow-up': '#16a34a' };
        document.getElementById('sum-notes').innerHTML = list.length ? list.map(n => {
            const c = colors[n.status] || '#64748b';
            const li = [];
            if (n.masalah) li.push(`<li>Issue: ${esc(n.masalah)}</li>`);
            if (n.tindakan) li.push(`<li>Action: ${esc(n.tindakan)}</li>`);
            if (n.pic) li.push(`<li>PIC: ${esc(n.pic)}</li>`);
            if (n.target) li.push(`<li>Target: ${esc(n.target)}</li>`);
            if (n.estimasi !== null && n.estimasi !== undefined) li.push(`<li>Estimated potential savings: <strong>${rp(n.estimasi)}</strong></li>`);
            return `<div class="sum-reco-item sum-reco">
                <div class="sum-reco-no" style="background:${c}">${esc(n.no)}</div>
                <div class="flex-fill"><div class="d-flex justify-content-between"><strong style="color:${c};font-size:.85rem">${modeLbl()} ${esc(n.ocf)}${n.buyer ? ' &middot; ' + esc(n.buyer) : ''}</strong>
                ${n.status ? `<span class="sum-badge" style="background:${c}22;color:${c}">${esc(n.status)}</span>` : ''}</div>
                <ul>${li.join('')}</ul></div></div>`;
        }).join('') : '<div class="text-muted small text-center py-3">No Recommendations &amp; Notes yet. Fill them in via Template &rarr; Import &rarr; Recommendations &amp; Notes.</div>';
    }

    function load() {
        Swal.fire({ title: 'Loading summary...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
        fetch(`${dataUrl}?${qs()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(d => {
                Swal.close();
                document.getElementById('sum-period-label').textContent =
                    `Mode : By ${modeLbl()} | Data periode : ${d.period.label} (comparing date ${d.period.prev_from} - ${d.period.prev_to})`;
                const empty = d.kpi.total_ocf.value === 0;
                document.getElementById('sum-empty').style.display = empty ? '' : 'none';
                document.getElementById('sum-body').style.display = empty ? 'none' : '';
                if (empty) return;
                renderTop3(d);
                renderTable(d.top_impact); renderNotes(d.notes);
                if (d.grade_filled < d.kpi.total_ocf.value) {
                    document.getElementById('sum-period-label').insertAdjacentHTML('beforeend',
                        ` &nbsp;|&nbsp; <span class="badge badge-warning">Grade filled ${d.grade_filled}/${d.kpi.total_ocf.value} ${modeLbl()}</span>`);
                }
            })
            .catch(err => { Swal.close(); Swal.fire('Failed', 'Unable to load summary: ' + err.message, 'error'); });
    }

    // Change lock status via SweetAlert
    document.querySelector('#sum-top-impact tbody').addEventListener('click', (e) => {
        const btn = e.target.closest('.sum-status-edit');
        if (!btn) return;
        e.preventDefault();
        const { lockId, ocf, status } = btn.dataset;
        if (!lockId) {
            Swal.fire('Failed', 'Lock ID not found in the summary data. Make sure RekonSummaryService.php is updated.', 'error');
            return;
        }
        const options = Object.fromEntries(lockTypes.map(t => [t, label(t)]));

        Swal.fire({
            title: `Change status of ${modeLbl()} ${ocf}`,
            input: 'select',
            inputOptions: options,
            inputValue: status,
            showCancelButton: true,
            confirmButtonText: 'Save',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: (value) => {
                if (value === status) return Swal.showValidationMessage('Status has not changed.');
                return fetch(statusUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lock_id: Number(lockId), lock_type: value }),
                })
                    .then(async (res) => {
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok || !json.success) throw new Error(json.message || 'Failed to change status.');
                        return json;
                    })
                    .catch((err) => Swal.showValidationMessage(err.message));
            },
        }).then((r) => {
            if (r.isConfirmed && r.value) {
                Swal.fire({ icon: 'success', title: 'Success', text: r.value.message, timer: 1500, showConfirmButton: false }).then(load);
            }
        });
    });

    document.getElementById('sum-apply').addEventListener('click', load);
    document.querySelectorAll('#sum-mode [data-mode]').forEach(b => b.addEventListener('click', () => {
        if (b.dataset.mode === mode) return;
        mode = b.dataset.mode;
        applyMode();
        load();
    }));
    document.getElementById('sum-template').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = `${templateUrl}?${qs()}`;
    });
    document.getElementById('sum-notes-template').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = `${notesTemplateUrl}?${qs()}`;
    });

    function uploadExcel(url, fileEl, title) {
        const file = fileEl.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        fd.append('date_from', fromEl.value);
        fd.append('date_to', toEl.value);
        fd.append('mode', mode);
        Swal.fire({ title, html: 'Please wait, processing the Excel file.', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
            .then(async (res) => {
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success) {
                    const detail = Array.isArray(json.errors) ? json.errors.map(esc).join('<br>') : '';
                    throw new Error(esc(json.message || 'Import failed.') + (detail ? `<br><br><small style="text-align:left;display:block">${detail}</small>` : ''));
                }
                return json;
            })
            .then((json) => Swal.fire({ icon: 'success', title: 'Success', html: esc(json.message) }).then(load))
            .catch((err) => Swal.fire({ icon: 'error', title: 'Failed', html: err.message }))
            .finally(() => { fileEl.value = ''; });
    }

    const fileEl = document.getElementById('sum-file');
    const notesFileEl = document.getElementById('sum-notes-file');
    document.getElementById('sum-import').addEventListener('click', (e) => { e.preventDefault(); fileEl.click(); });
    document.getElementById('sum-notes-import').addEventListener('click', (e) => { e.preventDefault(); notesFileEl.click(); });
    fileEl.addEventListener('change', () => uploadExcel(importUrl, fileEl, 'Importing grades...'));
    notesFileEl.addEventListener('change', () => uploadExcel(notesImportUrl, notesFileEl, 'Importing recommendations & notes...'));

    applyMode();
    load();
})();
</script>
</body>
</html>