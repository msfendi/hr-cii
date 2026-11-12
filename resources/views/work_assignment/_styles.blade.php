<style>
    :root {
        --wa-primary: #4e73df;
        --wa-primary-dark: #224abe;
        --wa-soft: #f4f6fd;
        --wa-border: #e3e8f4;
        --wa-text: #2c3e50;
        --wa-muted: #6b7688;
    }

    body {
        font-family: 'Nunito', sans-serif;
        background: linear-gradient(135deg, #4e73df 0%, #224abe 55%, #1a3a8f 100%);
        min-height: 100vh;
    }

    .wa-shell {
        max-width: 1080px;
        margin: 0 auto;
        padding: 1.5rem 1rem 1.75rem;
    }

    /* ── Header ── */
    .wa-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .wa-head .brand {
        display: flex;
        align-items: center;
        gap: .75rem;
        color: #fff;
    }

    .wa-head .brand img {
        height: 40px;
        width: auto;
        filter: brightness(0) invert(1);
    }

    .wa-head .brand .t1 {
        font-weight: 800;
        font-size: .95rem;
        line-height: 1.2;
    }

    .wa-head .brand .t2 {
        font-size: .74rem;
        opacity: .85;
        line-height: 1.2;
    }

    .wa-head-actions {
        display: flex;
        gap: .5rem;
        align-items: center;
    }

    .wa-head-actions .btn {
        border-radius: 2rem;
        font-weight: 600;
        font-size: .82rem;
        padding: .45rem 1rem;
    }

    .wa-head-user {
        color: #fff;
        font-size: .8rem;
        line-height: 1.25;
        text-align: right;
        margin-right: .35rem;
    }

    .wa-head-user b {
        font-weight: 800;
    }

    .wa-head-user span {
        opacity: .82;
        font-size: .72rem;
    }

    /* ── Kartu utama ── */
    .wa-card {
        background: #fff;
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 1rem 2.5rem rgba(15, 30, 80, .22);
        overflow: hidden;
    }

    .wa-card-head {
        background: var(--wa-primary);
        color: #fff;
        padding: 1.05rem 1.5rem;
    }

    .wa-card-head h4 {
        font-weight: 800;
        margin: 0;
        font-size: 1.02rem;
    }

    .wa-card-head .sub {
        font-size: .76rem;
        opacity: .88;
        margin-top: .12rem;
    }

    .wa-card-head .doc-ref {
        display: inline-block;
        background: rgba(255, 255, 255, .18);
        border-radius: 2rem;
        padding: .18rem .65rem;
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: .04em;
        white-space: nowrap;
    }

    .wa-card-body {
        padding: 1.35rem 1.5rem 1.5rem;
    }

    /* ── Section ── */
    .wa-section {
        margin-bottom: 1.4rem;
    }

    .wa-section:last-of-type {
        margin-bottom: .5rem;
    }

    .wa-section-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--wa-primary);
        margin-bottom: .75rem;
    }

    .wa-section-title::after {
        content: '';
        flex: 1 1 auto;
        height: 1px;
        background: var(--wa-border);
    }

    .wa-section-title .step {
        width: 21px;
        height: 21px;
        border-radius: 50%;
        background: var(--wa-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .66rem;
        font-weight: 800;
        flex: 0 0 21px;
    }

    /* ── Card karyawan (COMPACT: 1 baris) ── */
    .emp-card {
        background: var(--wa-soft);
        border: 1px solid var(--wa-border);
        border-radius: .7rem;
        padding: .7rem .9rem;
        display: flex;
        align-items: center;
        gap: .9rem;
        flex-wrap: wrap;
    }

    .emp-avatar {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        border-radius: 50%;
        background: var(--wa-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: .95rem;
        text-transform: uppercase;
    }

    .emp-id {
        min-width: 0;
        flex: 0 1 auto;
    }

    .emp-name {
        font-weight: 800;
        font-size: .88rem;
        color: var(--wa-text);
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .emp-npk {
        font-size: .72rem;
        color: var(--wa-muted);
        line-height: 1.2;
    }

    /* chips: Jabatan / Bagian / Section */
    .emp-chips {
        display: flex;
        gap: .3rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
        justify-content: flex-end;
    }

    .emp-chip {
        font-size: .68rem;
        font-weight: 700;
        background: #fff;
        border: 1px solid #cdd7ef;
        color: var(--wa-primary-dark);
        border-radius: 2rem;
        padding: .12rem .5rem;
        white-space: nowrap;
    }

    .emp-chip b {
        color: var(--wa-muted);
        font-weight: 600;
    }

    .emp-chip.empty {
        color: var(--wa-muted);
        border-style: dashed;
        border-color: #d8dfea;
        font-weight: 500;
        font-style: italic;
    }

    /* ── Input ── */
    .wa-label {
        font-size: .78rem;
        font-weight: 700;
        color: #3d4a5c;
        margin-bottom: .28rem;
        display: block;
    }

    .wa-label .req {
        color: #e74a3b;
        margin-left: .1rem;
    }

    .wa-label .hint {
        display: block;
        font-weight: 500;
        font-size: .71rem;
        color: var(--wa-muted);
        margin-top: .08rem;
    }

    .wa-input,
    .wa-textarea {
        border: 1px solid #d8dfee;
        border-radius: .55rem;
        padding: .55rem .8rem;
        font-size: .875rem;
        color: var(--wa-text);
        width: 100%;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }

    .wa-input:focus,
    .wa-textarea:focus {
        outline: 0;
        border-color: var(--wa-primary);
        box-shadow: 0 0 0 3px rgba(78, 115, 223, .14);
    }

    .wa-input[readonly].auto-field {
        background: #f8f9fc;
        border-style: dashed;
        border-color: #cdd5e5;
        color: var(--wa-muted);
    }

    .wa-input.is-invalid,
    .wa-textarea.is-invalid {
        border-color: #e74a3b;
    }

    .wa-textarea {
        resize: vertical;
        min-height: 72px;
    }

    .wa-row {
        display: flex;
        gap: .5rem;
        align-items: stretch;
    }

    .wa-row .wa-input {
        flex: 1 1 auto;
    }

    .wa-row .btn {
        border-radius: .55rem;
        padding: .55rem 1.05rem;
        font-size: .85rem;
        font-weight: 600;
        flex: 0 0 auto;
        background: var(--wa-primary);
        border-color: var(--wa-primary);
    }

    /* ── Chip tanggal ── */
    .date-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .55rem;
    }

    .date-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: #fff;
        border: 1px solid #cdd7ef;
        color: var(--wa-primary-dark);
        border-radius: 2rem;
        padding: .18rem .5rem .18rem .65rem;
        font-size: .74rem;
        font-weight: 700;
    }

    .date-chip .x {
        cursor: pointer;
        color: #98a4bb;
        font-size: .82rem;
        line-height: 1;
        border: 0;
        background: 0;
        padding: 0 .05rem;
    }

    .date-chip .x:hover {
        color: #e74a3b;
    }

    .date-empty {
        font-size: .75rem;
        color: var(--wa-muted);
        font-style: italic;
        margin-top: .5rem;
    }

    .auto-badge {
        display: inline-block;
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #7a869a;
        background: #eef1f7;
        border-radius: 2rem;
        padding: .05rem .4rem;
        margin-left: .35rem;
    }

    /* ── Panel info ── */
    .wa-note {
        background: #fffaf0;
        border: 1px solid #fde68a;
        border-radius: .6rem;
        padding: .65rem .85rem;
        font-size: .77rem;
        color: #78350f;
    }

    .wa-note.info {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .wa-note .t {
        font-weight: 800;
        display: block;
        margin-bottom: .12rem;
    }

    /* ── Alur approval ── */
    .flow {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
    }

    .flow-step {
        flex: 1 1 200px;
        background: #fff;
        border: 1px solid var(--wa-border);
        border-radius: .7rem;
        padding: .7rem .85rem;
        margin-bottom: .5rem;
        min-height: 92px;
        display: flex;
        flex-direction: column;
    }

    .flow-step .lv {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 21px;
        height: 21px;
        border-radius: 50%;
        background: var(--wa-primary);
        color: #fff;
        font-size: .64rem;
        font-weight: 800;
        margin-right: .35rem;
        vertical-align: middle;
    }

    .flow-step .role {
        font-size: .64rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--wa-primary);
        vertical-align: middle;
    }

    .flow-step .who {
        font-weight: 800;
        font-size: .84rem;
        color: var(--wa-text);
        margin-top: .3rem;
    }

    .flow-step .npk {
        font-size: .71rem;
        color: var(--wa-muted);
        margin-top: auto;
        padding-top: .2rem;
    }

    .flow-arrow {
        align-self: center;
        color: #c3ccdd;
        font-size: .85rem;
        padding: 0 .55rem;
        margin-bottom: .5rem;
    }

    @media (max-width: 767px) {
        .flow-arrow {
            display: none;
        }
    }

    /* ── Footer aksi ── */
    .wa-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        border-top: 1px solid var(--wa-border);
        padding: 1.05rem 0 .2rem;
        margin-top: .2rem;
    }

    .wa-actions .btn {
        border-radius: 2rem;
        font-weight: 700;
        font-size: .85rem;
        padding: .6rem 1.5rem;
        background: var(--wa-primary);
        border-color: var(--wa-primary);
    }

    .wa-actions .btn:hover {
        background: var(--wa-primary-dark);
        border-color: var(--wa-primary-dark);
    }

    .wa-actions .summary {
        font-size: .76rem;
        color: var(--wa-muted);
    }

    .is-loading {
        opacity: .6;
        pointer-events: none;
    }
</style>
