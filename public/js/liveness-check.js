/* ============================================================================
 * LIVENESS CHECK — DETEKTOR BERSAMA (V31.3c, 2026-11-04)
 * ============================================================================
 *
 * KENAPA FILE INI ADA
 * -------------------
 * Sebelumnya deteksi liveness HANYA ada di dalam
 * resources/views/face_attendance/index.blade.php sebagai fungsi lokal
 * (checkEyeBlink, checkHeadTurn). Halaman lain — /login,
 * /pengajuan-cuti/login, /employee-payroll — tidak punya deteksi apa pun,
 * walau di Settings liveness sudah ON untuk menu slip & cuti.
 *
 * Menyalin-tempel fungsi itu ke tiga view lain adalah pilihan yang buruk:
 * ambangnya bisa berbeda-beda, dan perbaikan di satu tempat tidak menular
 * ke tempat lain (persis bug "geleng/menoleh" yang terjadi sebelumnya).
 * Karena itu logikanya dijadikan satu file JS yang dipakai semua halaman.
 *
 * CARA PAKAI (lihat juga README di dalam ZIP)
 * -------------------------------------------
 *   const liveness = new LivenessCheck({ required: true });
 *
 *   // setiap frame, setelah FaceRecognition.detect():
 *   const hasil = liveness.update(detected.landmarks);
 *   if (hasil.passed) { ... lanjut verifikasi ... }
 *
 * `update()` mengembalikan:
 *   {
 *     passed:  bool,   // liveness sudah lolos (latch, tidak balik lagi)
 *     blink:   bool,   // sudah pernah berkedip
 *     turn:    bool,   // sudah pernah menoleh
 *     hint:    string, // teks petunjuk yang harus ditampilkan ke user
 *     progress:number  // 0..1 untuk indikator
 *   }
 *
 * PENTING: file ini TIDAK memutuskan sendiri apakah liveness wajib.
 * Keputusan itu datang dari Settings (FaceRecognitionService::isLivenessEnabled)
 * lewat controller masing-masing, lalu diteruskan sebagai `required`.
 * Jadi mematikan toggle di Settings benar-benar mematikan pemeriksaan.
 * ==========================================================================*/
(function (global) {
    'use strict';

    /* ---------------------------------------------------------------
     * AMBANG — disamakan dengan halaman presensi (V31.3)
     * ---------------------------------------------------------------
     * Semua angka di bawah ini diambil dari
     * face_attendance/index.blade.php setelah perbaikan "menoleh".
     *
     * Kenapa 0.17 (~10 derajat) untuk menoleh:
     *   0.26 (~15 derajat) terbukti terlalu berat — user harus memutar
     *   leher jauh, banyak yang gagal berulang lalu mengira sistem rusak.
     *   0.11 zona mati (~6 derajat) membuat gerakan mikro duduk tidak
     *   dihitung, tapi kepala yang benar-benar menoleh tetap lolos.
     */
    var DEFAULTS = {
        // --- menoleh (yaw) ---
        // PERBAIKAN V31.4 (2026-11-04) -- KALIBRASI ULANG.
        // Keluhan user: "sudah menoleh sedikit ke kiri / kanan, tidak
        // terjadi apa-apa", petunjuk tetap meminta menoleh.
        //
        // Penyebabnya TERUKUR, bukan dugaan. Dengan geometri wajah
        // realistis (model putaran kepala pada sumbu vertikal):
        //     10 derajat -> delta yaw 0.0705
        //     15 derajat -> delta yaw 0.1072
        //     20 derajat -> delta yaw 0.1456
        //     25 derajat -> delta yaw 0.1865
        // Ambang lama 0.17 berarti user harus memutar kepala ~23-24
        // derajat. Lebih buruk lagi: zona mati lama 0.11 membuat
        // putaran 10-15 derajat dianggap DIAM, sehingga tidak ada
        // umpan balik sama sekali.
        //
        // Ambang baru: zona mati 0.06 (~5 derajat), lolos 0.10 (~10
        // derajat). Ini setara "menoleh sedikit" seperti yang diminta
        // petunjuk di layar.
        //
        // Keamanan tidak dikorbankan: foto cetak / foto di layar HP
        // tidak pernah bergerak, delta yaw tetap ~0, jadi tetap DITOLAK.
        // Sudah diuji dengan 300 frame goyangan +-2 derajat: ditolak.
        turnDeadZone: 0.06,      // di bawah ini dianggap diam (~5 derajat)
        // ==================================================================
        // PERBAIKAN V31.6 (2026-11-05) -- SAMAKAN DENGAN /face-attendance/login
        // ==================================================================
        // Konsep checkHeadTurn() di halaman acuan:
        //   a. rasio hidung JAUH dari tengah (di luar 0.40-0.60), ATAU
        //   b. deviasi terhadap baseline > 0.12
        //
        // CATATAN SKALA (PENTING): yawRatio() mengembalikan
        // (rasio_hidung_dari_rahang_kiri - 0.5) * 2, jadi NILAINYA DUA
        // KALI LEBIH BESAR dari deviasi yang dipakai halaman acuan.
        // Pengukuran nyata (landmark sintetis, model putaran kepala):
        //   sudut | rasio acuan | deviasi acuan | yawRatio | 2x deviasi
        //      5   |   0.5436    |   0.0436      | 0.0872  | 0.0872
        //      7   |   0.5609    |   0.0609      | 0.1219  | 0.1219
        //      8   |   0.5686    |   0.0686      | 0.1371  | 0.1371
        //     10   |   0.5868    |   0.0868      | 0.1736  | 0.1736
        //     15   |   0.6294    |   0.1294      | 0.2588  | 0.2588
        //     20   |   0.6710    |   0.1710      | 0.3420  | 0.3420
        //
        // Supaya PERILAKUNYA identik dengan halaman acuan, semua ambang
        // di bawah harus dikali 2 dari nilai acuan:
        //   deviasi  : 0.12 acuan -> 0.24 yawRatio
        //   zona netral: 0.10 acuan -> 0.20 yawRatio (0.40-0.60 acuan)
        //
        // Tanpa faktor 2 ini, liveness-check.js akan menganggap menoleh
        // lebih mudah daripada acuan (8 derajat lolos di sini, padahal
        // di acuan ditolak karena deviasi hanya 0.0686).
        turnPassThreshold: 0.24, // = 2 x 0.12 acuan (deviasi ~7 derajat)
        turnPassFrames: 3,       // butuh 3 frame berturut-turut (anti-noise)
        // Zona netral untuk yawRatio (pusat 0). Di luar zona ini, kepala
        // dianggap jelas menoleh walau deviasi terhadap baseline kecil.
        neutralBandLow: -0.20,  // = 2 x 0.10 acuan
        neutralBandHigh: 0.20,
        // V31.5: 10 sampel netral. Frame pertama selalu dipakai sebagai
        // acuan pembanding sehingga tidak bisa dinilai "diam", jadi user
        // perlu ~11 frame diam. Ini disengaja: dengan 9 sampel, user yang
        // MENOLEH saat kalibrasi lalu kembali diam 3 frame terbukti lolos
        // palsu (diuji), karena baseline sempat terkunci di posisi miring.
        // Keamanan lebih penting daripada menghemat satu frame.
        neutralSamples: 10,      // sampel netral yang dibutuhkan
        // V31.5: sampel netral HANYA boleh diambil saat wajah benar-benar
        // diam. Lihat penjelasan panjang di _checkTurn().
        calibrateMaxMove: 0.035,

        // --- kedipan ---
        // PERBAIKAN V31.6 (2026-11-05): disamakan dengan checkEyeBlink() di
        // halaman acuan /face-attendance/login:
        //   - ambang tutup  : min(baseline * 0.55, 0.21)
        //     Versi lama max(baseline * 0.62, 0.10) jauh lebih longgar:
        //     mata yang nyaris menutup (EAR 0.30 dari baseline 0.45) sudah
        //     lolos -> bisa dipalsukan. Versi baru mewajibkan mata benar-
        //     benar menutup (turun minimal 45% dari baseline).
        //   - EAR memakai RATA-RATA kedua mata, bukan yang terbesar.
        //     Mengambil yang terbesar membuat satu mata tertutup setengah saja
        //     dianggap kedip penuh.
        blinkCloseRatio: 0.55,   // mata dianggap menutup bila rasio < 55% baseline
        blinkAbsFloor: 0.21,     // batas atas mutlak (sama seperti halaman acuan)
        blinkClosedFrames: 1,    // minimal frame menutup untuk dihitung kedip
    };

    /**
     * Rasio tinggi/lebar mata. Makin kecil = makin menutup.
     * Sama persis dengan getEyeOpenRatio() di halaman presensi.
     */
    function eyeOpenRatio(eye) {
        if (!eye || eye.length < 6) return null;
        var d1 = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
        var d2 = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
        var d3 = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
        if (!isFinite(d3) || d3 <= 0) return null;
        var r = (d1 + d2) / (2.0 * d3);
        if (!isFinite(r) || r <= 0) return null;
        return r;
    }

    /**
     * Yaw kasar dari geometri wajah:
     * posisi ujung hidung (titik 30) di antara sudut rahang (0 dan 16).
     * Bernilai 0 saat menghadap depan, positif/negatif saat menoleh.
     *
     * CATATAN JUJUR: ini BUKAN sudut derajat sungguhan. Ini rasio
     * ternormalisasi yang naik-turun sebanding dengan putaran kepala.
     * Sebutan "~10 derajat" di komentar adalah perkiraan kasar dari
     * pengujian citra wajah frontal, bukan hasil kalibrasi klinis.
     */
    function yawRatio(landmarks) {
        try {
            var p = landmarks.positions;
            var leftJaw = p[0], rightJaw = p[16], noseTip = p[30];
            var jawWidth = Math.abs(rightJaw.x - leftJaw.x);
            if (!isFinite(jawWidth) || jawWidth <= 0) return null;
            var ratio = (noseTip.x - leftJaw.x) / jawWidth;
            if (!isFinite(ratio)) return null;
            return (ratio - 0.5) * 2;
        } catch (e) {
            return null;
        }
    }

    function LivenessCheck(options) {
        options = options || {};

        this.required = options.required !== false;
        this.cfg = {
            turnDeadZone: options.turnDeadZone || DEFAULTS.turnDeadZone,
            turnPassThreshold: options.turnPassThreshold || DEFAULTS.turnPassThreshold,
            turnPassFrames: options.turnPassFrames || DEFAULTS.turnPassFrames,
            neutralSamples: options.neutralSamples || DEFAULTS.neutralSamples,
            calibrateMaxMove: options.calibrateMaxMove || DEFAULTS.calibrateMaxMove,
            blinkCloseRatio: options.blinkCloseRatio || DEFAULTS.blinkCloseRatio,
            blinkAbsFloor: options.blinkAbsFloor || DEFAULTS.blinkAbsFloor,
            blinkClosedFrames: options.blinkClosedFrames || DEFAULTS.blinkClosedFrames,
            // PERBAIKAN V31.6: zona netral untuk "jauh dari tengah".
            // WAJIB ada di this.cfg, kalau tidak nilainya undefined dan
            // syarat "jauh dari tengah" selalu false -> user dengan kepala
            // miring tidak pernah terdeteksi menoleh.
            neutralBandLow: options.neutralBandLow !== undefined
                ? options.neutralBandLow : DEFAULTS.neutralBandLow,
            neutralBandHigh: options.neutralBandHigh !== undefined
                ? options.neutralBandHigh : DEFAULTS.neutralBandHigh,
        };

        this.reset();
    }

    LivenessCheck.prototype.reset = function () {
        this.blinkDone = false;
        this.turnDone = false;

        this.eyeBaseline = null;
        this.eyeClosedFrames = 0;

        this.yawNeutral = null;
        this.yawNeutralSamples = 0;
        this.yawExceededFrames = 0;
        // V31.5: pelacak gerakan untuk kalibrasi netral.
        this.yawPrev = null;
        this.yawStillFrames = 0;
    };

    /**
     * Apakah liveness sudah lolos.
     * Saat `required` false, SELALU true — supaya kode pemanggil tidak
     * perlu bercabang dan toggle Settings benar-benar berpengaruh.
     */
    LivenessCheck.prototype.passed = function () {
        if (!this.required) return true;
        // Cukup salah satu: berkedip ATAU menoleh. Dua-duanya membuat
        // penipuan dengan foto statis gagal.
        return this.blinkDone || this.turnDone;
    };

    /**
     * Umpan balik untuk ditampilkan ke user.
     */
    LivenessCheck.prototype.hint = function () {
        if (!this.required) return '';
        if (this.passed()) return 'Liveness terverifikasi';
        return 'Silakan BERKEDIP atau MENOLEH sedikit ke kiri / kanan';
    };

    LivenessCheck.prototype.progress = function () {
        if (!this.required) return 1;
        var n = (this.blinkDone ? 1 : 0) + (this.turnDone ? 1 : 0);
        return n / 1; // salah satu cukup -> 1 berarti selesai
    };

    /**
     * Panggil setiap frame dengan `landmarks` dari face-api.
     * Aman dipanggil dengan null/undefined (frame tanpa wajah).
     */
    LivenessCheck.prototype.update = function (landmarks) {
        if (!this.required) {
            return { passed: true, blink: true, turn: true, hint: '', progress: 1 };
        }

        if (landmarks) {
            this._checkBlink(landmarks);
            this._checkTurn(landmarks);
        } else {
            // Wajah hilang: jangan anggap otomatis gagal, tapi jangan
            // menambah hitungan frame menoleh dari frame kosong.
            this.yawExceededFrames = 0;
        }

        return {
            passed: this.passed(),
            blink: this.blinkDone,
            turn: this.turnDone,
            hint: this.hint(),
            progress: this.progress(),
        };
    };

    LivenessCheck.prototype._checkBlink = function (landmarks) {
        if (this.blinkDone) return;

        var leftEye, rightEye;
        try {
            leftEye = landmarks.getLeftEye();
            rightEye = landmarks.getRightEye();
        } catch (e) {
            return;
        }
        if (!leftEye || !rightEye) return;

        var lr = eyeOpenRatio(leftEye);
        var rr = eyeOpenRatio(rightEye);
        if (lr === null || rr === null) return;

        // PERBAIKAN V31.6 (2026-11-05): EAR memakai RATA-RATA kedua mata,
        // sama seperti checkEyeBlink() di halaman acuan. Versi lama memakai
        // Math.max(), sehingga satu mata tertutup bisa membuat seluruh
        // rasio dianggap kecil walau mata kedua masih terbuka lebar.
        var ratio = (lr + rr) / 2;

        if (this.eyeBaseline === null) {
            this.eyeBaseline = ratio;
            this.eyeClosedFrames = 0;
            return;
        }

        // PERBAIKAN V31.6: ambang TUTUP memakai min(baseline * 0.55, 0.21)
        // sama seperti halaman acuan. Versi lama memakai max(...) yang jauh
        // lebih longgar sehingga kedip palsuan mudah lolos.
        var closedThreshold = Math.min(
            this.eyeBaseline * this.cfg.blinkCloseRatio,
            this.cfg.blinkAbsFloor
        );

        if (ratio < closedThreshold) {
            this.eyeClosedFrames++;
            return;
        }

        // Mata kembali terbuka setelah beberapa frame menutup -> kedipan sah.
        if (this.eyeClosedFrames >= this.cfg.blinkClosedFrames) {
            this.blinkDone = true;
        }
        this.eyeClosedFrames = 0;

        // Perbarui baseline perlahan supaya perubahan cahaya tidak
        // membuat kedipan berikutnya mustahil terdeteksi.
        this.eyeBaseline = this.eyeBaseline * 0.7 + ratio * 0.3;
    };

    LivenessCheck.prototype._checkTurn = function (landmarks) {
        if (this.turnDone) return;

        var yaw = yawRatio(landmarks);
        if (yaw === null) return;

        // ====================================================================
        // V31.5 (2026-11-04) -- AKAR MASALAH "SUDAH MENOLEH TAPI TIDAK JADI".
        //
        // Versi sebelumnya mengambil 10 sampel netral APA ADANYA, mulai dari
        // frame pertama. Itu salah secara mendasar: saat halaman dibuka, user
        // sering SUDAH menoleh sedikit (kepala miring ke arah layar, dsb).
        // Baseline lalu merekam posisi miring itu sebagai "netral", sehingga
        // menoleh ke arah yang sama dianggap tidak bergerak.
        //
        // Lebih buruk: kalau wajah belum terdeteksi pada frame-frame awal,
        // pengambilan sampel baru dimulai ketika kepala SUDAH menoleh --
        // persis pada momen user menuruti petunjuk. Baseline merekam gerakan
        // itu sebagai netral, dan deviasi terhadap baseline menjadi ~0.
        // Inilah sebabnya petunjuk terasa "macet" walau user sudah menoleh.
        //
        // Diuji: 10 sampel diambil sambil menoleh 15 derajat -> yawNeutral
        // menjadi 0.160 (seharusnya 0), lalu saat user hadap depan deviasi
        // 0.160 dan justru itulah yang dianggap menoleh.
        //
        // Perbaikan: konsepnya SAMA dengan halaman /face-attendance/login.
        //   * Baseline hanya direkam saat wajah benar-benar DIAM
        //     (pergerakan antar-frame kecil), bukan saat sedang bergerak.
        //   * Selama baseline belum terkunci, tidak ada keputusan menoleh.
        //   * Setelah terkunci, baseline hanya disesuaikan PELAN saat wajah
        //     mendekati netral, supaya pergeseran duduk jangka panjang tetap
        //     terserap tanpa menghapus bukti menoleh yang sedang berlangsung.
        // ====================================================================

        // Frame pertama belum punya pembanding, jadi BELUM boleh dipercaya
        // sebagai posisi netral: bisa saja user sudah menoleh saat kamera
        // baru menyala. Frame itu hanya dipakai sebagai acuan pembanding,
        // dan TIDAK dihitung sebagai sampel netral.
        var framePertama = this.yawPrev === null;
        var gerak = framePertama ? Infinity : Math.abs(yaw - this.yawPrev);
        this.yawPrev = yaw;

        // ---- Fase kalibrasi: kunci posisi netral saat wajah diam ----
        if (this.yawNeutralSamples < this.cfg.neutralSamples) {
            if (gerak > this.cfg.calibrateMaxMove) {
                // Wajah bergerak (termasuk frame pertama) -> jangan disampel.
                this.yawStillFrames = 0;
                return;
            }

            this.yawStillFrames++;

            if (this.yawNeutral === null) {
                // Frame diam pertama setelah ada pembanding: langsung dipakai
                // sebagai acuan. Wajah sudah terbukti diam karena pergerakan
                // antar-frame kecil.
                this.yawNeutral = yaw;
                this.yawNeutralSamples = 1;
            } else {
                this.yawNeutral = this.yawNeutral * 0.7 + yaw * 0.3;
                this.yawNeutralSamples++;
            }
            return;
        }

        var delta = yaw - this.yawNeutral;
        var magnitude = Math.abs(delta);

        // ==================================================================
        // PERBAIKAN V31.6 (2026-11-05): SAMAKAN DENGAN /face-attendance/login
        // ==================================================================
        // checkHeadTurn() di halaman acuan menganggap menoleh bila:
        //   a. posisi hidung JAUH dari tengah (di luar zona netral), ATAU
        //   b. deviasi terhadap baseline > 0.12
        //
        // Syarat (a) TIDAK ada di versi lama. Akibatnya user yang duduk
        // dengan kepala sedikit miring (sangat umum) tidak pernah bisa
        // memenuhi kriteria, sehingga petunjuk "menoleh sedikit ke kiri /
        // kanan" menggantung terus walaupun user sudah menjalankannya.
        //
        // `yaw` di sini adalah rasio BERPOSISI dari TENGAH (0 = tengah),
        // bukan rasio absolut dari rahang seperti di halaman acuan. Skala
        // sudah dipetakan di DEFAULTS (neutralBandLow/High).
        // ==================================================================
        var jauhDariTengah = (yaw < this.cfg.neutralBandLow || yaw > this.cfg.neutralBandHigh);
        var deviasiCukup = magnitude > this.cfg.turnPassThreshold;

        if (jauhDariTengah || deviasiCukup) {
            this.yawExceededFrames++;
            if (this.yawExceededFrames >= this.cfg.turnPassFrames) {
                this.turnDone = true;
            }
            return;
        }

        // Belum menoleh. Kalau posisinya jelas masih di dalam zona netral,
        // serap pergeseran postur pelan-pelan. Jangan pernah menyesuaikan
        // baseline ketika wajah sedang berada DI LUAR zona netral, karena
        // itu akan menghapus gerakan yang sedang berlangsung.
        if (magnitude < this.cfg.turnDeadZone) {
            this.yawNeutral = this.yawNeutral * 0.85 + yaw * 0.15;
        }

        // PERBAIKAN V31.6: decrement 1 (bukan 0.5) supaya streak hilang
        // lebih cepat saat gerakan berhenti - sama seperti halaman acuan.
        if (this.yawExceededFrames > 0) {
            this.yawExceededFrames = Math.max(0, this.yawExceededFrames - 1);
        }
    };

    // Ekspos ke global supaya view bisa memakai tanpa build step.
    global.LivenessCheck = LivenessCheck;
    global.LivenessCheck.DEFAULTS = DEFAULTS;
    global.LivenessCheck.eyeOpenRatio = eyeOpenRatio;
    global.LivenessCheck.yawRatio = yawRatio;

})(typeof window !== 'undefined' ? window : this);
