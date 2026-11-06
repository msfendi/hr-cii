/* ============================================================================
 * RANDOM CHALLENGE — ANTI-KECURANGAN FACE ATTENDANCE (V1, 2026-11-09)
 * ============================================================================
 *
 * KENAPA FILE INI ADA
 * -------------------
 * Liveness check yang sudah ada (public/js/liveness-check.js) selalu memakai
 * tantangan yang SAMA: berkedip ATAU menoleh. Tantangan itu bisa direkam
 * sekali lalu diputar ulang untuk menipu sistem.
 *
 * Random challenge menutup celah itu: sebelum wajah dicocokkan, sistem
 * meminta user melakukan gerakan yang DIPILIH SECARA ACAK dari:
 *   - hadap ke kiri
 *   - hadap ke kanan
 *   - buka mulut
 *
 * Karena tantangannya acak dan tidak diketahui lebih dulu, rekaman lama
 * tidak bisa memenuhi tantangan yang sedang aktif.
 *
 * PENTING — TIDAK ADA HUBUNGANNYA DENGAN LIVENESS
 * -----------------------------------------------
 * File ini berdiri sendiri. Ia tidak mengimpor, tidak mengubah, dan tidak
 * bergantung pada LivenessCheck. Ambang liveness, urutan pemeriksaan liveness,
 * dan seluruh logika liveness TIDAK DISENTUH sama sekali. Random challenge
 * hanya menambah satu syarat tambahan yang berdiri sendiri:
 *
 *     if (randomChallenge wajib && !challengeSelesai) -> belum boleh lanjut
 *
 * Kalau random challenge dimatikan di settings, instantiate dengan
 * { required: false } dan langsung dianggap selesai.
 *
 * CARA PAKAI
 * ----------
 *   const challenge = new RandomChallenge({ required: true });
 *   // server kirim challenge target via endpoint / JSON:
 *   challenge.setTarget('left');   // 'left' | 'right' | 'mouth'
 *
 *   // setiap frame, setelah FaceRecognition.detect():
 *   const hasil = challenge.update(detected.landmarks);
 *   if (hasil.passed) { ... lanjut ke liveness / match ... }
 *
 *   // reset kalau halaman di-reload atau user mengulang:
 *   challenge.reset();
 *
 * APA YANG DIKEMBALIKAN update()
 * -----------------------------
 *   {
 *     passed:   bool,   // tantangan aktif sudah terpenuhi (latch)
 *     target:   string, // challenge yang sedang aktif ('left'|'right'|'mouth')
 *     hint:     string, // teks petunjuk untuk ditampilkan ke user
 *     progress: number  // 0..1 untuk indikator
 *   }
 *
 * CATATAN PENTING SOAL "HADAP KIRI / KANAN"
 * ------------------------------------------
 * Nama challenge memakai arah DARI SUDUT KAMERA (yaitu arah yang USER
 * harus gerakkan kepala untuk MENUJU arah tersebut). Jadi:
 *   - 'left'  -> user memutar kepala ke arah KIRI layar
 *   - 'right' -> user memutar kepala ke arah KANAN layar
 *
 * Detector memakai landmark 0 (rahang kiri), 16 (rahang kanan), 30 (ujung
 * hidung), 48 (mulut kiri), 54 (mulut kanan) dari face-api.js.
 *
 * KAMERA MIRROR (penting)
 * ----------------------
 * Preview kamera depan di halaman face-attendance ditampilkan dengan
 * transform scaleX(-1), sehingga yang user LIHAT di layar terbalik dari
 * koordinat frame video. Landmark face-api dihitung pada frame ASLI
 * (tidak dimirror), jadi:
 *
 *   - user memutar kepala ke KIRI layar  -> di frame video hidung bergerak
 *     ke arah "rahang kanan" frame       -> yawRatio menjadi POSITIF
 *   - user memutar kepala ke KANAN layar -> di frame video hidung bergerak
 *     ke arah "rahang kiri" frame        -> yawRatio menjadi NEGATIF
 *
 * Artinya tanda yaw TERBALIK dibanding arah yang dilihat user. Kalau tidak
 * dibalik, challenge "hadap kiri" akan dianggap "hadap kanan" (atau
 * sebaliknya) sehingga tidak pernah bisa lolos.
 *
 * Solusi: landmark DINORMALISASI ke koordinat yang terlihat user dengan
 * membalik tanda yawRatio. Ini JANGAN menyentuh logika liveness check --
 * koreksi hanya berlaku untuk random challenge.
 * ==========================================================================*/
(function (global) {
    'use strict';

    /* -------------------------------------------------------------------
     * AMBANG
     * -------------------------------------------------------------------
     * turnPassThreshold : deviation dari baseline netral yang dianggap
     *                      "cukup menoleh". Nilai ini dipakai untuk challenge
     *                      hadap kiri / kanan.
     *
     * mouthOpenRatio    : rasio tinggi-opening mulut terhadap lebar mulut.
     *                      > ambang = mulut dianggap terbuka.
     *
     * passFrames        : jumlah frame berturut-turut yang harus memenuhi
     *                      syarat, supaya challenge tidak lolos karena satu
     *                      frame noise / deteksi meleset.
     *
     * missToleranceFrames : frame tanpa wajah yang ditoleransi. Saat user
     *                      menoleh, wajah bisa sesaat keluar kotak deteksi.
     *                      Tanpa toleransi, progres akan sering ter-reset dan
     *                      user frustrasi (persis masalah yang sudah pernah
     *                      diperbaiki pada liveness V14).
     */
    var DEFAULTS = {
        turnPassThreshold: 0.22,       // ~10 derajat, longgar sedikit dari acuan
        turnNeutralBand: 0.18,         // di luar pita ini = jelas menoleh
        mouthOpenRatio: 0.42,          // mulut terbuka jelas
        mouthClosedRatio: 0.28,        // interpolate: nilai di bawah ini = tertutup
        passFrames: 3,
        neutralSamples: 8,
        calibrateMaxMove: 0.035,
        missToleranceFrames: 12,
        retryResetFrames: 20,
        // Preview kamera depan di halaman face-attendance memakai
        // transform scaleX(-1). Landmark face-api dihitung pada frame
        // video ASLI (tidak dimirror), sehingga arah kiri/kanan pada
        // landmark berlawanan dengan yang terlihat user.
        // Set true supaya challenge "hadap kiri"/"hadap kanan" tidak
        // terbalik. Set false bila kamera tidak dimirror.
        mirrored: true
    };

    /* -------------------------------------------------------------------
     * Helper geometri
     * -------------------------------------------------------------------*/

    /**
     * Rasio posisi hidung di antara dua sudut rahang.
     * 0 =nose di tengah (hadap depan), negatif = ke arah rahang kiri,
     * positif = ke arah rahang kanan.
     *
     * INI BUKAN sudut derajat sungguhan, melainkan rasio ternormalisasi.
     *
     * PENTIK (kamera mirror): landmark dihitung pada frame video ASLI,
     * sedangkan user melihat preview yang dimirror. Untuk kamera depan
     * yang dimirror, arah yang dilihat user berlawanan dengan koordinat
     * frame, jadi tanda rasio harus dibalik agar "kiri" = kiri layar.
     *
     * @param {Object} landmarks hasil deteksi face-api
     * @param {boolean} mirrored true bila preview kamera dimirror
     */
    function yawRatio(landmarks, mirrored) {
        try {
            var p = landmarks.positions;
            var leftJaw = p[0], rightJaw = p[16], noseTip = p[30];
            if (!leftJaw || !rightJaw || !noseTip) return null;

            var jawWidth = Math.abs(rightJaw.x - leftJaw.x);
            if (!isFinite(jawWidth) || jawWidth <= 0) return null;

            var ratio = (noseTip.x - leftJaw.x) / jawWidth;
            if (!isFinite(ratio)) return null;

            var yaw = (ratio - 0.5) * 2;

            // Normalisasi ke arah yang terlihat user.
            // Camera depan mirrored -> balik tanda.
            return mirrored ? -yaw : yaw;
        } catch (e) {
            return null;
        }
    }

    /**
     * Rasio tinggi lubang mulut terhadap lebar mulut.
     * Makin besar = mulut makin terbuka.
     */
    function mouthOpenRatio(landmarks) {
        try {
            var p = landmarks.positions;
            // 48 & 54 = sudut mulut kiri & kanan (outer lips)
            // 62 & 66 = sudut mulut dalam atas & bawah (inner lips)
            var mL = p[48], mR = p[54];
            var iTop = p[62], iBottom = p[66];
            if (!mL || !mR || !iTop || !iBottom) return null;

            var width = Math.abs(mR.x - mL.x);
            if (!isFinite(width) || width <= 0) return null;

            var height = Math.abs(iBottom.y - iTop.y);
            if (!isFinite(height)) return null;

            return height / width;
        } catch (e) {
            return null;
        }
    }

    /* -------------------------------------------------------------------
     * RandomChallenge
     * -------------------------------------------------------------------*/
    function RandomChallenge(options) {
        options = options || {};

        this.required = options.required !== false;

        this.cfg = {
            turnPassThreshold: options.turnPassThreshold !== undefined
                ? options.turnPassThreshold : DEFAULTS.turnPassThreshold,
            turnNeutralBand: options.turnNeutralBand !== undefined
                ? options.turnNeutralBand : DEFAULTS.turnNeutralBand,
            mouthOpenRatio: options.mouthOpenRatio !== undefined
                ? options.mouthOpenRatio : DEFAULTS.mouthOpenRatio,
            mouthClosedRatio: options.mouthClosedRatio !== undefined
                ? options.mouthClosedRatio : DEFAULTS.mouthClosedRatio,
            passFrames: options.passFrames !== undefined
                ? options.passFrames : DEFAULTS.passFrames,
            neutralSamples: options.neutralSamples !== undefined
                ? options.neutralSamples : DEFAULTS.neutralSamples,
            calibrateMaxMove: options.calibrateMaxMove !== undefined
                ? options.calibrateMaxMove : DEFAULTS.calibrateMaxMove,
            missToleranceFrames: options.missToleranceFrames !== undefined
                ? options.missToleranceFrames : DEFAULTS.missToleranceFrames,
            retryResetFrames: options.retryResetFrames !== undefined
                ? options.retryResetFrames : DEFAULTS.retryResetFrames,
            mirrored: options.mirrored !== undefined
                ? !!options.mirrored : DEFAULTS.mirrored
        };

        // Target challenge yang akan diminta. Kalau required=false, apa pun
        // target-nya akan langsung dianggap selesai.
        this.target = options.target || null;

        this.reset();
    }

    /**
     * Daftar challenge yang tersedia. Dipakai juga untuk random pick.
     */
    RandomChallenge.CHALLENGES = ['left', 'right', 'mouth'];

    /**
     * Petakan challenge ke teks petunjuk Bahasa Indonesia.
     *
     * CATATAN: teks memakai arah LAYAR (kiri/kanan yang terlihat user di
     * preview), bukan arah badan user. Karena preview kamera depan
     * dimirror, "ke kiri layar" sama dengan user memiringkan kepala ke
     * arah yang terlihat di sisi kiri preview. Menambahkan kata "layar"
     * supaya tidak ambigu.
     */
    RandomChallenge.HINTS = {
        left:  'Hadapkan wajah ke KIRI layar',
        right: 'Hadapkan wajah ke KANAN layar',
        mouth: 'BUKA MULUT Anda'
    };

    /**
     * Reset seluruh state challenge. Dipanggil saat halaman reload atau
     * user menekan tombol "Ulangi".
     */
    RandomChallenge.prototype.reset = function (newTarget) {
        this.done = false;

        if (newTarget !== undefined && newTarget !== null) {
            this.target = newTarget;
        }

        // state kalibrasi
        this.yawNeutral = null;
        this.yawNeutralSamples = 0;
        this.yawPrev = null;
        this.yawStillFrames = 0;

        // state mulut
        this.mouthBaseline = null;

        // state streak
        this.passStreak = 0;

        // counter frame tanpa wajah
        this.missFrames = 0;
    };

    /**
     * Tetapkan target challenge. Nilai valid: 'left' | 'right' | 'mouth'.
     * Memanggil ini me-reset streak (tantangan baru = mulai dari nol).
     */
    RandomChallenge.prototype.setTarget = function (target) {
        if (RandomChallenge.CHALLENGES.indexOf(target) === -1) {
            return false;
        }
        if (this.target !== target) {
            this.reset(target);
        } else {
            this.done = false;
            this.passStreak = 0;
        }
        return true;
    };

    /**
     * Apakah challenge sudah terpenuhi.
     * Saat `required` false, SELALU true — supaya pemanggil tidak perlu
     * bercabang dan toggle settings benar-benar berpengaruh.
     */
    RandomChallenge.prototype.passed = function () {
        if (!this.required) return true;
        return this.done === true;
    };

    /**
     * Teks petunjuk yang harus ditampilkan ke user.
     */
    RandomChallenge.prototype.hint = function () {
        if (!this.required) return '';
        if (this.passed()) return 'Tantangan terverifikasi';

        var base = RandomChallenge.HINTS[this.target] || 'Ikuti tantangan yang diminta';

        // Saat kalibrasi baseline belum selesai, beri tahu user untuk diam
        // sebentar supaya baseline terekam dengan benar.
        if (this.yawNeutralSamples < this.cfg.neutralSamples && this.target !== 'mouth') {
            return 'Posisikan wajah lurus ke kamera, jangan bergerak sebentar';
        }

        return base;
    };

    /**
     * Progres 0..1 untuk indikator.
     */
    RandomChallenge.prototype.progress = function () {
        if (!this.required) return 1;
        if (this.done) return 1;

        var streakRatio = Math.min(1, this.passStreak / this.cfg.passFrames);

        // Kalibrasi baseline juga dianggap bagian dari progres, supaya
        // user tidak bingung kenapa baris tidak bergerak.
        var calibRatio = 0;
        if (this.target !== 'mouth') {
            calibRatio = Math.min(1, this.yawNeutralSamples / this.cfg.neutralSamples);
        } else {
            calibRatio = this.mouthBaseline !== null ? 1 : 0.5;
        }

        // Kalibrasi = 40% progres, gerakan = 60%.
        return (calibRatio * 0.4) + (streakRatio * 0.6);
    };

    /**
     * Panggil setiap frame dengan `landmarks` dari face-api.
     * Aman dipanggil dengan null/undefined (frame tanpa wajah).
     */
    RandomChallenge.prototype.update = function (landmarks) {
        if (!this.required) {
            return { passed: true, target: this.target, hint: '', progress: 1 };
        }

        // Belum ada target = tidak ada yang bisa diperiksa. Jangan dianggap
        // lolos: biarkan pemanggil yang menentukan target lebih dulu.
        if (!this.target) {
            return { passed: false, target: null, hint: 'Menyiapkan tantangan...', progress: 0 };
        }

        if (this.done) {
            return { passed: true, target: this.target, hint: this.hint(), progress: 1 };
        }

        if (landmarks) {
            this.missFrames = 0;

            if (this.target === 'mouth') {
                this._checkMouth(landmarks);
            } else {
                this._checkTurn(landmarks);
            }
        } else {
            this.missFrames += 1;

            // Wajah hilang terlalu lama -> reset streak (bukan seluruh state).
            if (this.missFrames > this.cfg.missToleranceFrames) {
                this.passStreak = 0;
                this.yawPrev = null;
                this.yawStillFrames = 0;
            }
        }

        return {
            passed: this.passed(),
            target: this.target,
            hint: this.hint(),
            progress: this.progress()
        };
    };

    /**
     * Cek challenge "buka mulut".
     */
    RandomChallenge.prototype._checkMouth = function (landmarks) {
        var ratio = mouthOpenRatio(landmarks);
        if (ratio === null) return;

        // Baseline: erste few frame dipakai sebagai referensi "mulut tertutup".
        if (this.mouthBaseline === null) {
            this.mouthBaseline = ratio;
            this.passStreak = 0;
            return;
        }

        // Baseline pelan-pelan menyesuaikan saat mulut tertutup, supaya
        // user's face shape berbeda antar orang tidak bikin challenge
        // mustahil (e.g.ENCI someone who never fully closes mouth).
        if (ratio < this.mouthBaseline) {
            this.mouthBaseline = this.mouthBaseline * 0.85 + ratio * 0.15;
        }

        // Rasio relatif terhadap baseline user sendiri.
        var relative = this.mouthBaseline > 0
            ? ratio / this.mouthBaseline
            : ratio;

        var isOpen = ratio >= this.cfg.mouthOpenRatio
            || (relative >= 1.8 && ratio > this.cfg.mouthClosedRatio);

        if (isOpen) {
            this.passStreak += 1;
            if (this.passStreak >= this.cfg.passFrames) {
                this.done = true;
            }
        } else {
            this.passStreak = 0;
        }
    };

    /**
     * Cek challenge "hadap kiri" / "hadap kanan".
     */
    RandomChallenge.prototype._checkTurn = function (landmarks) {
        // PENTIK: teruskan flag mirror supaya tanda yaw selalu memakai
        // arah yang terlihat user (bukan arah koordinat frame video).
        var yaw = yawRatio(landmarks, this.cfg.mirrored);
        if (yaw === null) return;

        var framePertama = this.yawPrev === null;
        var gerak = framePertama ? Infinity : Math.abs(yaw - this.yawPrev);
        this.yawPrev = yaw;

        // ---- Fase kalibrasi: rekam baseline netral saat wajah diam ----
        if (this.yawNeutralSamples < this.cfg.neutralSamples) {
            if (gerak > this.cfg.calibrateMaxMove) {
                this.yawStillFrames = 0;
                return;
            }

            this.yawStillFrames += 1;

            if (this.yawNeutral === null) {
                this.yawNeutral = yaw;
                this.yawNeutralSamples = 1;
            } else {
                this.yawNeutral = this.yawNeutral * 0.7 + yaw * 0.3;
                this.yawNeutralSamples += 1;
            }
            return;
        }

        var delta = yaw - this.yawNeutral;
        var magnitude = Math.abs(delta);

        // Jauh dari tengah = jelas menoleh.
        var jauhDariTengah = (yaw < -this.cfg.turnNeutralBand || yaw > this.cfg.turnNeutralBand);
        var deviasiCukup = magnitude > this.cfg.turnPassThreshold;

        // Arah yang diminta. Penting: tanda deviasi harus COCOK dengan arah,
        // kalau tidak user "menoleh ke kanan" tapi challenges-nya 'left' bisa
        // tetap lolos karena ambang memakai nilai absolut.
        var arahBenar = false;
        if (this.target === 'left') {
            // Menoleh ke kiri -> yaw menjadi NEGATIF (hidung mendekati rahang kiri).
            arahBenar = (yaw < -this.cfg.turnNeutralBand) || (delta < -this.cfg.turnPassThreshold);
        } else if (this.target === 'right') {
            arahBenar = (yaw > this.cfg.turnNeutralBand) || (delta > this.cfg.turnPassThreshold);
        }

        if (arahBenar && (jauhDariTengah || deviasiCukup)) {
            this.passStreak += 1;
            if (this.passStreak >= this.cfg.passFrames) {
                this.done = true;
            }
            return;
        }

        // Belum memenuhi: reset streak.
        this.passStreak = 0;

        // Serap pergeseran postur pelan-pelan saat masih di zona netral.
        if (magnitude < 0.06 && !jauhDariTengah) {
            this.yawNeutral = this.yawNeutral * 0.85 + yaw * 0.15;
        }
    };

    /**
     * Pilih challenge acak (client-side fallback).
     * Preferensi: pakai server bila ada; fungsi ini hanya cadangan.
     */
    RandomChallenge.prototype.pickRandom = function (exclude) {
        var pool = RandomChallenge.CHALLENGES.slice();
        if (exclude && pool.indexOf(exclude) !== -1) {
            pool = pool.filter(function (c) { return c !== exclude; });
        }
        var idx = Math.floor(Math.random() * pool.length);
        var picked = pool[idx];
        this.reset(picked);
        return picked;
    };

    global.RandomChallenge = RandomChallenge;

})(typeof window !== 'undefined' ? window : this);
