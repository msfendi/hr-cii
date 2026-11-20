{{--
| Komponen bersama: daftar approver (NPK + nama + status badge).
| Mengikuti pola Leave Approval (`status_approver`): tiap approver satu baris,
| kiri = badge NPK + nama, kanan = badge status.
--}}
<style>
    .wa-approvers {
        font-size: .86rem;
        text-align: left;
        min-width: 0;
    }

    .wa-approver {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 6px;
        padding: .35rem 0;
    }

    .wa-approver + .wa-approver {
        border-top: 1px dashed #e3e6f0;
    }

    .wa-approver .who {
        display: flex;
        align-items: flex-start;
        min-width: 0;
        flex: 1 1 auto;
    }

    .wa-approver .npk {
        flex: 0 0 auto;
        margin-right: .25rem;
        font-size: .72rem;
        font-weight: 600;
    }

    /* Nama approver boleh wrap sampai 3 baris (bukan ellipsis 1 baris). */
    .wa-approver .nm {
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        overflow-wrap: anywhere;
        word-break: break-word;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        font-size: .85rem;
        font-weight: 700;
        line-height: 1.25;
        color: #343a40;
    }

    .wa-approver .st {
        flex: 0 0 auto;
        margin-top: .05rem;
        font-size: .75rem;
        padding: 3px 7px;
    }

    .wa-approver.upcoming .nm,
    .wa-approver.skipped .nm {
        font-weight: 500;
        color: #6c757d;
    }

    .wa-approvers-empty {
        font-size: .8rem;
        color: #a0a6b2;
    }
</style>

<script>
    /**
     * Escape HTML (mandiri — halaman punya escapeHtml sendiri di scope lokal).
     */
    if (!window.waEscape) {
        window.waEscape = function (value) {
            var d = document.createElement('div');
            d.textContent = value == null ? '' : String(value);
            return d.innerHTML;
        };
    }

    /**
     * Render daftar approver: badge NPK + nama (kiri), badge status (kanan).
     * Satu approver = satu baris, dipisah garis putus-putus.
     *
     * @param {Array} steps  Timeline approval: {name, npk, role_label, decision}
     * @returns {string} HTML
     */
    window.waApproverBadges = function (steps) {
        var badges = {
            approved:   { cls: 'badge-success', icon: 'fa-check',   label: 'Disetujui' },
            rejected:   { cls: 'badge-danger', icon: 'fa-times',   label: 'Ditolak' },
            waiting:    { cls: 'badge-warning text-white', icon: 'fa-clock', label: 'Menunggu' },
            upcoming:   { cls: 'badge-light border text-muted', icon: 'fa-hourglass-half', label: 'Berikutnya' },
            skipped:    { cls: 'badge-secondary', icon: 'fa-minus',  label: 'Dilewati' }
        };

        if (!steps || !steps.length) {
            return '<span class="wa-approvers-empty">-</span>';
        }

        return '<div class="wa-approvers">' + steps.map(function (s) {
            var decision = s.decision || 'upcoming';
            var b = badges[decision] || badges.upcoming;
            var name = s.name || 'Nama belum tersedia';
            var npk = s.npk || '-';
            var title = (s.role_label || 'Approver') + ' — ' + b.label + ' [' + npk + '] ' + name;

            return '<div class="wa-approver ' + waEscape(decision) + '" title="' + waEscape(title) + '">'
                + '<div class="who">'
                + '<span class="badge badge-light border text-muted npk">' + waEscape(npk) + '</span>'
                + '<span class="nm">' + waEscape(name) + '</span>'
                + '</div>'
                + '<span class="badge ' + b.cls + ' st">'
                + '<i class="fas ' + b.icon + ' fa-xs mr-1"></i>' + waEscape(b.label)
                + '</span>'
                + '</div>';
        }).join('') + '</div>';
    };
</script>
