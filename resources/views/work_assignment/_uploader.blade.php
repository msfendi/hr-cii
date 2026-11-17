{{--
| Komponen bersama: area upload lampiran Work Assignment (drag & drop).
--}}
<style>
    .wa-dropzone {
        position: relative;
        border: 1.5px dashed #c6d2e8;
        border-radius: .75rem;
        background: #fbfcff;
        padding: 1.4rem 1rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s, background .15s, box-shadow .15s;
    }

    .wa-dropzone:hover {
        border-color: var(--wa-primary);
        background: #f6f9ff;
    }

    .wa-dropzone.is-drag {
        border-color: var(--wa-primary);
        background: #eef3ff;
        box-shadow: 0 0 0 4px rgba(78, 115, 223, .1);
    }

    .wa-dropzone.is-full {
        opacity: .6;
        pointer-events: none;
    }

    .wa-dropzone .dz-ico {
        width: 42px;
        height: 42px;
        margin: 0 auto .55rem;
        border-radius: 50%;
        background: #eaf0ff;
        color: var(--wa-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
    }

    .wa-dropzone .dz-title {
        font-size: .85rem;
        font-weight: 700;
        color: var(--wa-text);
    }

    .wa-dropzone .dz-title u {
        color: var(--wa-primary);
        text-decoration: none;
    }

    .wa-dropzone .dz-hint {
        font-size: .72rem;
        color: var(--wa-muted);
        margin-top: .2rem;
    }

    /* ── Kartu file ── */
    .wa-files {
        display: grid;
        gap: .45rem;
        margin-top: .7rem;
    }

    .wa-file {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem .65rem;
        background: #fff;
        border: 1px solid var(--wa-border);
        border-radius: .55rem;
        box-shadow: 0 1px 2px rgba(15, 30, 80, .04);
        animation: wa-file-in .18s ease;
    }

    @keyframes wa-file-in {
        from { opacity: 0; transform: translateY(-3px); }
        to   { opacity: 1; transform: none; }
    }

    .wa-file .f-ico {
        flex: 0 0 auto;
        width: 32px;
        height: 32px;
        border-radius: .4rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        background: #eef2fb;
        color: var(--wa-primary);
    }

    .wa-file .f-ico.is-pdf   { background: #fdeceb; color: #c0392b; }
    .wa-file .f-ico.is-image { background: #e8f6f0; color: #13855c; }
    .wa-file .f-ico.is-word  { background: #e9f0fd; color: #2b579a; }
    .wa-file .f-ico.is-sheet { background: #e6f5ea; color: #1d6f42; }

    .wa-file .f-body {
        flex: 1 1 auto;
        min-width: 0;
    }

    .wa-file .f-name {
        font-size: .8rem;
        font-weight: 700;
        color: var(--wa-text);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .wa-file .f-meta {
        font-size: .69rem;
        color: var(--wa-muted);
        margin-top: .05rem;
    }

    .wa-file .f-remove {
        flex: 0 0 auto;
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: .3rem;
        background: transparent;
        color: #a6adbb;
        cursor: pointer;
        font-size: .8rem;
        transition: background .12s, color .12s;
    }

    .wa-file .f-remove:hover {
        background: #fdeceb;
        color: #c0392b;
    }

    /* ── Mobile ── */
    @media (max-width: 640px) {
        .wa-dropzone {
            padding: 1.15rem .8rem;
        }

        .wa-dropzone .dz-ico {
            width: 34px;
            height: 34px;
            font-size: .9rem;
            margin-bottom: .4rem;
        }

        .wa-dropzone .dz-title {
            font-size: .76rem;
        }

        .wa-dropzone .dz-hint {
            font-size: .64rem;
        }

        .wa-file {
            padding: .42rem .55rem;
            gap: .5rem;
        }

        .wa-file .f-ico {
            width: 26px;
            height: 26px;
            font-size: .78rem;
        }

        .wa-file .f-name {
            font-size: .74rem;
        }

        .wa-file .f-meta {
            font-size: .64rem;
        }

        .wa-file .f-remove {
            width: 22px;
            height: 22px;
            font-size: .72rem;
        }
    }
</style>

<script>
    /**
     * Area upload drag & drop.
     *
     * @param {Object} opts { inputId, listId, maxFiles, maxKb, allowed }
     */
    window.waInitUploader = function (opts) {
        opts = opts || {};

        var input  = document.getElementById(opts.inputId);
        var zone   = document.getElementById(opts.zoneId);
        var list   = document.getElementById(opts.listId);
        var empty  = document.getElementById(opts.emptyId);

        if (!input || !zone || !list) return;

        var maxFiles = opts.maxFiles || 5;
        var maxKb    = opts.maxKb || 5120;

        var ICONS = {
            pdf:   { cls: 'is-pdf',   icon: 'fa-file-pdf' },
            jpg:   { cls: 'is-image', icon: 'fa-file-image' },
            jpeg:  { cls: 'is-image', icon: 'fa-file-image' },
            png:   { cls: 'is-image', icon: 'fa-file-image' },
            webp:  { cls: 'is-image', icon: 'fa-file-image' },
            doc:   { cls: 'is-word',  icon: 'fa-file-word' },
            docx:  { cls: 'is-word',  icon: 'fa-file-word' },
            xls:   { cls: 'is-sheet', icon: 'fa-file-excel' },
            xlsx:  { cls: 'is-sheet', icon: 'fa-file-excel' }
        };

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function humanSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function extOf(name) {
            return (name.split('.').pop() || '').toLowerCase();
        }

        /** File yang sudah ada + baru digabung; input asli diset ulang lalu di-rebuild. */
        function syncInput(files) {
            var dt = new DataTransfer();
            files.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
        }

        function currentFiles() {
            return Array.prototype.slice.call(input.files || []);
        }

        function render() {
            var files = currentFiles();

            list.innerHTML = '';

            if (empty) empty.style.display = files.length ? 'none' : 'block';

            zone.classList.toggle('is-full', files.length >= maxFiles);

            files.forEach(function (f, idx) {
                var ext = extOf(f.name);
                var meta = ICONS[ext] || { cls: '', icon: 'fa-file' };

                var row = document.createElement('div');
                row.className = 'wa-file';
                row.innerHTML =
                    '<span class="f-ico ' + meta.cls + '"><i class="fas ' + meta.icon + '"></i></span>'
                    + '<span class="f-body">'
                    + '<span class="f-name" title="' + esc(f.name) + '">' + esc(f.name) + '</span>'
                    + '<span class="f-meta">' + humanSize(f.size) + '</span>'
                    + '</span>'
                    + '<button type="button" class="f-remove" title="Hapus file" aria-label="Hapus ' + esc(f.name) + '">'
                    + '<i class="fas fa-times"></i></button>';

                row.querySelector('.f-remove').addEventListener('click', function () {
                    var next = currentFiles().filter(function (_, i) { return i !== idx; });
                    syncInput(next);
                    render();
                });

                list.appendChild(row);
            });
        }

        function addFiles(incoming) {
            var files = currentFiles();
            var errs = [];

            Array.prototype.forEach.call(incoming, function (f) {
                if (files.length >= maxFiles) {
                    errs.push('Maksimal ' + maxFiles + ' file per pengajuan.');
                    return;
                }
                if (f.size > maxKb * 1024) {
                    errs.push(f.name + ' melebihi ' + (maxKb / 1024) + ' MB.');
                    return;
                }
                files.push(f);
            });

            syncInput(files);
            render();

            if (errs.length && window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Lampiran', text: errs.join(' '), timer: 3200, showConfirmButton: false });
            }
        }

        // Zone → klik buka file picker
        zone.addEventListener('click', function () { input.click(); });
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
        });

        // Drag & drop
        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('is-drag');
            });
        });

        ['dragleave', 'drop'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('is-drag');
            });
        });

        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files) {
                addFiles(e.dataTransfer.files);
            }
        });

        input.addEventListener('change', function () { render(); });

        render();
    };
</script>
