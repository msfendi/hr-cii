{{--
| Komponen bersama: daftar lampiran di modal detail (Manage + Approval).
| Butuh JS global `waRenderAttachments(attachments, opts)`.
--}}
<script>
    /**
     * Escape HTML (shared, dilindungi dari definisi ganda).
     */
    if (!window.waEscape) {
        window.waEscape = function (value) {
            var d = document.createElement('div');
            d.textContent = value == null ? '' : String(value);
            return d.innerHTML;
        };
    }

    /**
     * Render daftar lampiran (nama, ukuran, tombol unduh, tombol hapus).
     *
     * @param {Array}  attachments  Format output WorkAssignmentAttachment::format()
     * @param {Object} opts         { downloadBase, deletable, requestId }
     * @returns {string} HTML
     */
    window.waRenderAttachments = function (attachments, opts) {
        opts = opts || {};

        if (!attachments || !attachments.length) {
            return '<div class="text-muted small"><i class="fas fa-paperclip mr-1"></i>Tidak ada lampiran.</div>';
        }

        var html = '<div class="wa-att-list">';

        attachments.forEach(function (a, i) {
            var download = opts.downloadBase
                ? '<a class="wa-att-btn" href="' + waEscape(opts.downloadBase + '/' + i) + '" title="Unduh">'
                  + '<i class="fas fa-download"></i></a>'
                : '';

            var remove = opts.deletable
                ? '<button type="button" class="wa-att-btn danger btn-att-delete" '
                  + 'data-request-id="' + waEscape(opts.requestId) + '" data-index="' + i + '" '
                  + 'data-name="' + waEscape(a.name) + '" title="Hapus lampiran">'
                  + '<i class="fas fa-trash"></i></button>'
                : '';

            html += '<div class="wa-att-item' + (a.exists ? '' : ' missing') + '">'
                + '<span class="ico"><i class="fas ' + waEscape(a.icon || 'fa-file') + '"></i></span>'
                + '<span class="nm" title="' + waEscape(a.name) + '">' + waEscape(a.name) + '</span>'
                + '<span class="sz">' + waEscape(a.size || '-') + '</span>'
                + (a.exists ? '' : '<span class="badge badge-secondary">hilang</span>')
                + download + remove
                + '</div>';
        });

        html += '</div>';

        return html;
    };
</script>

<style>
    .wa-att-list {
        display: grid;
        gap: .3rem;
    }

    .wa-att-item {
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .32rem .5rem;
        background: #f7f9fc;
        border: 1px solid #e4e9f1;
        border-radius: .35rem;
        font-size: .8rem;
        color: #4a5568;
    }

    .wa-att-item.missing { border-color: #f1dedb; background: #fffafa; }

    .wa-att-item .ico {
        flex: 0 0 auto;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: .28rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e6ecf6;
        color: #4e73df;
        font-size: .75rem;
    }

    .wa-att-item .nm {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 600;
    }

    .wa-att-item .sz {
        flex: 0 0 auto;
        font-size: .72rem;
        color: #8a93a3;
    }

    .wa-att-btn {
        flex: 0 0 auto;
        width: 1.6rem;
        height: 1.6rem;
        border-radius: .25rem;
        border: 1px solid #dde3ec;
        background: #fff;
        color: #4e73df;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        text-decoration: none;
    }

    .wa-att-btn:hover { background: #eef2f9; }

    .wa-att-btn.danger { color: #c0392b; border-color: #f0d8d5; }
    .wa-att-btn.danger:hover { background: #fdeeed; }
</style>
