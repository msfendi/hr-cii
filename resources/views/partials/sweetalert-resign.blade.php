{{--
    ============================================================================
    PARTIAL: KARYAWAN RESIGN (PKWT.TKK terisi) - SweetAlert seragam
    ============================================================================
    Dipakai di 3 tempat:
      1. face_recognition/self_register.blade.php  (step 1 daftar wajah)
      2. face_attendance/login.blade.php            (login wajah)
      3. face_attendance/index.blade.php           (kirim presensi wajah)

    Include dengan:
        @include('partials.sweetalert-resign')

    Lalu panggil di JS:
        showResignAlert({
            message : 'pesan dari server (opsional)',
            action  : 'Pendaftaran wajah tidak dapat dilanjutkan.',
                       // 'Login & presensi ditolak. Silakan hubungi HRD.'
                       // 'Presensi tidak dapat dikirim. Silakan hubungi HRD.'
            npk     : 'C-00001'          // (opsional)
        });

    CATATAN LOGIKA (tidak diubah oleh partial ini):
      - PKWT.TKK NULL     -> karyawan AKTIF, boleh lanjut.
      - PKWT.TKK terisi   -> karyawan SUDAH RESIGN, ditolak + alert ini.
--}}

<style>
    /* ---------- Isolasi container SweetAlert2 vs layout halaman ----------
       Halaman self-register / face-login memakai `body { display:flex;
       align-items:center; justify-content:center; }`.
       SweetAlert2 v11 menambah class `swal2-height-auto` pada <body> yang
       memaksa `height:auto !important`. Akibatnya dokumen tergeser
       (html.scrollWidth melonjak, halaman ter-scroll horizontal).
       heightAuto:false (di JS) + aturan di bawah mencegah hal tersebut.
    */
    html.swal2-shown,
    body.swal2-shown{
        overflow-x:hidden !important;
    }
    body.swal2-shown{
        height:100% !important;
    }
    body.swal2-shown > .swal2-container{
        position:fixed !important;
        inset:0 !important;
        width:100% !important;
        max-width:100% !important;
        box-sizing:border-box !important;
        margin:0 !important;
        padding:10px !important;
    }

    /* ---------- SweetAlert "Karyawan Resign" ---------- */
    .swal-resign-popup{
        border-radius:16px !important;
        border-top:4px solid #dc3545 !important;
        box-shadow:0 20px 55px rgba(0,0,0,.45) !important;
    }
    .swal-resign-icon{
        width:78px !important;
        height:78px !important;
        margin:22px auto 8px auto !important;
        border-radius:50% !important;
        display:flex !important;
        align-items:center !important;
        justify-content:center !important;
        background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%) !important;
        border:2px solid #fca5a5 !important;
    }
    .swal-resign-icon i{
        color:#b91c1c !important;
        font-size:2.35rem !important;
        line-height:1 !important;
    }
    .swal-resign-title{
        font-size:1.22rem !important;
        font-weight:800 !important;
        color:#7f1d1d !important;
        margin:6px 0 10px 0 !important;
        line-height:1.35 !important;
    }
    .swal-resign-body{
        text-align:left !important;
        font-size:.9rem !important;
        color:#4b5563 !important;
        line-height:1.6 !important;
        padding:0 4px !important;
    }
    .swal-resign-msg{
        margin:0 0 12px 0 !important;
        padding:0 !important;
    }
    .swal-resign-box{
        display:flex !important;
        align-items:flex-start !important;
        gap:9px !important;
        background:#fef2f2 !important;
        border:1px solid #fecaca !important;
        border-left:4px solid #dc3545 !important;
        border-radius:10px !important;
        padding:11px 13px !important;
        color:#7f1d1d !important;
        font-size:.855rem !important;
        line-height:1.55 !important;
        text-align:left !important;
    }
    .swal-resign-box i{
        color:#dc3545 !important;
        font-size:.95rem !important;
        margin-top:2px !important;
        flex:0 0 auto !important;
    }
    .swal-resign-box b{
        color:#991b1b !important;
    }
    .swal-resign-npk{
        display:inline-block !important;
        background:#fee2e2 !important;
        color:#991b1b !important;
        border-radius:6px !important;
        padding:1px 7px !important;
        font-weight:700 !important;
        font-family:ui-monospace,SFMono-Regular,Menlo,monospace !important;
        font-size:.84rem !important;
    }
    .swal-resign-btn{
        background:#dc3545 !important;
        border:1px solid #b91c1c !important;
        border-radius:9px !important;
        font-weight:700 !important;
        font-size:.9rem !important;
        padding:.6rem 1.5rem !important;
        margin-top:4px !important;
        box-shadow:0 4px 12px rgba(220,53,69,.32) !important;
    }
    .swal-resign-btn:hover{
        background:#b91c1c !important;
        border-color:#991b1b !important;
    }
    .swal-resign-btn:focus{
        box-shadow:0 0 0 3px rgba(220,53,69,.30) !important;
    }
    /* Mobile: jangan terlalu besar, tetap terbaca */
    @media (max-width:575.98px){
        .swal-resign-icon{ width:64px !important; height:64px !important; margin:18px auto 6px auto !important; }
        .swal-resign-icon i{ font-size:1.9rem !important; }
        .swal-resign-title{ font-size:1.05rem !important; }
        .swal-resign-body{ font-size:.845rem !important; }
        .swal-resign-box{ font-size:.8rem !important; padding:10px 11px !important; }
    }
</style>

<script>
    /**
     * Tampilkan SweetAlert "Karyawan Resign" (PKWT.TKK terisi).
     *
     * @param {Object} opt
     *   opt.message : string  - pesan dari server (opsional)
     *   opt.action  : string  - kalimat tindakan yang ditolak (opsional)
     *   opt.npk     : string  - NPK karyawan (opsional)
     * @returns {Promise} hasil Swal.fire
     */
    function showResignAlert(opt) {
        opt = opt || {};

        var msg = opt.message
            ? String(opt.message)
            : 'Data kepegawaian Anda sudah tercatat keluar.';

        var action = opt.action
            ? String(opt.action)
            : 'Proses tidak dapat dilanjutkan.';

        var npkHtml = '';
        if (opt.npk) {
            npkHtml = '<div style="margin-top:10px">NPK: <span class="swal-resign-npk">'
                + String(opt.npk).replace(/[<>&"]/g, '') + '</span></div>';
        }

        if (typeof Swal === 'undefined' || !Swal || typeof Swal.fire !== 'function') {
            // SweetAlert tidak termuat -> fallback ke alert() bawaan browser.
            window.alert('Karyawan sudah resign - ' + msg + ' ' + action);
            return Promise.resolve({ isConfirmed: true, dismissed: false });
        }

        return Swal.fire({
            // Cegah SweetAlert2 v11 menambah class `swal2-height-auto` pada
            // <body>. Class itu memaksa `height:auto !important` yang merusak
            // halaman ber-layout flex (align/justify center) -> dokumen
            // tergeser dan terjadi horizontal overflow di mobile.
            heightAuto: false,
            html: '<div class="swal-resign-icon"><i class="fas fa-user-slash"></i></div>' +
                  '<div class="swal-resign-title">Karyawan Sudah Resign</div>' +
                  '<div class="swal-resign-body">' +
                    '<div class="swal-resign-msg">' + msg + '</div>' +
                    '<div class="swal-resign-box">' +
                      '<i class="fas fa-circle-exclamation"></i>' +
                      '<div><b>' + action + '</b><br>' +
                      'Silakan hubungi HRD bila data kepegawaian ini keliru.</div>' +
                    '</div>' + npkHtml +
                  '</div>',
            showConfirmButton: true,
            confirmButtonText: 'Mengerti',
            // SweetAlert2 v11 memakai customClass.confirmButton.
            // (confirmButtonClass sudah dihapus sejak v10 -> tidak berefek.)
            customClass: {
                popup: 'swal-resign-popup',
                confirmButton: 'swal-resign-btn'
            },
            buttonsStyling: false,
            focusConfirm: true
        });
    }
</script>
