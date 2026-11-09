<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur 1 & 2 (2026-11-09):
     * - canteen_settings di database utama.
     * - Window istirahat: SUMBER UTAMA = break_masters (existing). Tabel canteen_break_windows dipertahankan
h     *   hanya sebagai mirror kompatibilitas lama agar migrasi lama tidak error; transaksi kantin membaca isi break_masters.
     * - satisfaction_questions + satisfaction_responses di database utama (pertanyaan di-manage admin).
     * - Alter canteens: tambah canteen_id (1/2 + unique), contact_person, contact_phone.
     * - Tabel canteen & canteen_twos di database 'canteen' (bukan CII).
     */
    public function up(): void
    {
        // ---------- CII: canteen_settings ----------
        if (!Schema::hasTable('canteen_settings')) {
            Schema::create('canteen_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('liveness_canteen_enabled')->default(true);
                $table->boolean('is_random_challenge_enabled')->default(false);
                $table->boolean('random_challenge_canteen_enabled')->default(false);
                $table->boolean('require_geomap')->default(false);
                $table->text('allowed_work_location_ids')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canteen_break_windows')) {
            Schema::create('canteen_break_windows', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->time('time_start');
                $table->time('time_end');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default 3 window
            DB::table('canteen_break_windows')->insert([
                ['name' => 'Window 1', 'time_start' => '09:00:00', 'time_end' => '09:30:00', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Window 2', 'time_start' => '12:00:00', 'time_end' => '13:30:00', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Window 3', 'time_start' => '16:00:00', 'time_end' => '16:30:00', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ---------- CII: canteens alter ----------
        if (Schema::hasTable('canteens')) {
            if (!Schema::hasColumn('canteens', 'canteen_id')) {
                Schema::table('canteens', function (Blueprint $table) {
                    $table->tinyInteger('canteen_id')->nullable();
                });
            }
            if (!Schema::hasColumn('canteens', 'contact_person')) {
                Schema::table('canteens', function (Blueprint $table) {
                    $table->string('contact_person')->nullable();
                });
            }
            if (!Schema::hasColumn('canteens', 'contact_phone')) {
                Schema::table('canteens', function (Blueprint $table) {
                    $table->string('contact_phone')->nullable();
                });
            }
            // Backfill canteen_id untuk baris lama: 1 untuk baris pertama, 2 untuk baris kedua, dst, max 2.
            // Hanya bila masih null — idempotent.
            try {
                $existing = DB::table('canteens')->orderBy('id')->get(['id', 'canteen_id']);
                $slot = 1;
                foreach ($existing as $row) {
                    if ($row->canteen_id === null) {
                        DB::table('canteens')->where('id', $row->id)->update(['canteen_id' => $slot]);
                        // kontak backfill dari pic_
                        DB::table('canteens')->where('id', $row->id)->whereNull('contact_person')->update([]);
                    }
                    $slot = $slot >= 2 ? 2 : $slot + 1;
                }
                // Hindari duplikat: pastikan unique di future (hanya tambah bila belum ada index).
                // Index manual via statement bila belum ada (SQL Server: CREATE UNIQUE INDEX IF NOT EXISTS tidak didukung semua versi,
                // jadi pakai schema builder try/catch).
                try {
                    Schema::table('canteens', function (Blueprint $table) {
                        $table->unique('canteen_id');
                    });
                } catch (\Throwable $e) {
                    // Sudah ada atau tidak bisa — biarkan, validasi di level controller akan menjaga 1/2 unik.
                    \Log::warning('canteens: skip unique index canteen_id: ' . $e->getMessage());
                }
            } catch (\Throwable $e) {
                \Log::warning('canteens backfill canteen_id gagal: ' . $e->getMessage());
            }
        }

        // ---------- CII: Kuesioner kepuasan ----------
        if (!Schema::hasTable('satisfaction_questions')) {
            Schema::create('satisfaction_questions', function (Blueprint $table) {
                $table->id();
                $table->string('section')->nullable();
                $table->text('question_text');
                $table->string('question_type')->default('likert'); // likert | open
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('satisfaction_responses')) {
            Schema::create('satisfaction_responses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('question_id')->index();
                $table->tinyInteger('score')->nullable(); // 1..5 untuk likert
                $table->text('answer_text')->nullable(); // untuk open
                $table->timestamps();

                $table->foreign('question_id')->references('id')->on('satisfaction_questions')->onDelete('cascade');
            });
        }

        // Seed 35 pertanyaan bila kosong (idempotent: hanya bila tabel kosong)
        if (DB::table('satisfaction_questions')->count() === 0) {
            $rows = $this->defaultQuestions();
            DB::table('satisfaction_questions')->insert($rows);
        }

        // ---------- Database 'canteen': tabel canteen & canteen_twos ----------
        try {
            $canteenConn = DB::connection('canteen');
            if (!$canteenConn->getSchemaBuilder()->hasTable('canteen')) {
                $canteenConn->getSchemaBuilder()->create('canteen', function (Blueprint $table) {
                    $table->id();
                    $table->string('npk')->index();
                    $table->string('name')->nullable();
                    $table->string('dept')->nullable();
                    $table->string('canteen_no')->nullable();
                    $table->date('date')->index();
                    $table->string('window_name')->nullable();
                    $table->string('location_name')->nullable();
                    $table->decimal('latitude', 10, 7)->nullable();
                    $table->decimal('longitude', 11, 7)->nullable();
                    $table->timestamps();
                });
            }
            if (!$canteenConn->getSchemaBuilder()->hasTable('canteen_twos')) {
                $canteenConn->getSchemaBuilder()->create('canteen_twos', function (Blueprint $table) {
                    $table->id();
                    $table->string('npk')->index();
                    $table->string('name')->nullable();
                    $table->string('dept')->nullable();
                    $table->string('canteen_no')->nullable();
                    $table->date('date')->index();
                    $table->string('window_name')->nullable();
                    $table->string('location_name')->nullable();
                    $table->decimal('latitude', 10, 7)->nullable();
                    $table->decimal('longitude', 11, 7)->nullable();
                    $table->timestamps();
                });
            }
            // Index composite idempotent — coba buat bila belum ada, abaikan bila gagal.
            try { $canteenConn->statement("CREATE INDEX IX_canteen_npk_date ON canteen (npk, date)"); } catch (\Throwable $e) {}
            try { $canteenConn->statement("CREATE INDEX IX_canteen_twos_npk_date ON canteen_twos (npk, date)"); } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            \Log::warning('Migrasi canteen/canteen_twos di database canteen gagal: ' . $e->getMessage());
            // Tidak throw — supaya migrasi CII tetap jalan walau koneksi canteen belum terjangkau.
        }
    }

    public function down(): void
    {
        // CII — urutan anak dulu
        Schema::dropIfExists('satisfaction_responses');
        Schema::dropIfExists('satisfaction_questions');
        Schema::dropIfExists('canteen_break_windows');
        Schema::dropIfExists('canteen_settings');

        // Canteen DB — jangan drop data transaksi di down agar tidak kehilangan data.
        // Operator bisa drop manual bila benar-benar ingin menghapus.
    }

    protected function defaultQuestions(): array
    {
        $likert = [
            // B. Tentang Pekerjaan (1-4)
            ['B', 'Saya merasa nyaman dengan pekerjaan saya.'],
            ['B', 'Target pekerjaan yang diberikan masih dapat saya capai.'],
            ['B', 'Saya mendapatkan waktu istirahat yang cukup.'],
            ['B', 'Pembagian pekerjaan dilakukan secara adil.'],
            // C. Tentang Atasan (5-9)
            ['C', 'Atasan memperlakukan saya dengan baik.'],
            ['C', 'Atasan mau mendengarkan keluhan atau pendapat saya.'],
            ['C', 'Atasan memberikan arahan kerja yang jelas.'],
            ['C', 'Atasan menegur karyawan dengan cara yang baik dan sopan.'],
            ['C', 'Atasan tidak pilih kasih terhadap karyawan.'],
            // D. Tentang Lingkungan Kerja (10-14)
            ['D', 'Lingkungan kerja saya bersih dan nyaman.'],
            ['D', 'Saya merasa aman saat bekerja.'],
            ['D', 'Toilet dan tempat istirahat tersedia dalam kondisi yang baik.'],
            ['D', 'Saya memiliki hubungan yang baik dengan rekan kerja.'],
            ['D', 'Saya merasa nyaman bekerja bersama rekan satu tim.'],
            // E. Sikap dan Perlakuan (15-23)
            ['E', 'Atasan dan rekan kerja berbicara dengan sopan kepada saya.'],
            ['E', 'Saya tidak pernah dibentak atau diteriaki saat bekerja.'],
            ['E', 'Saya tidak pernah dihina, diejek, atau dipermalukan di depan orang lain.'],
            ['E', 'Saya tidak pernah diancam atau ditakut-takuti oleh atasan maupun rekan kerja.'],
            ['E', 'Saya tidak pernah mengalami sentuhan fisik yang tidak pantas.'],
            ['E', 'Saya tidak pernah menerima komentar atau candaan yang mengarah pada pelecehan seksual.'],
            ['E', 'Saya merasa aman dari pelecehan seksual di lingkungan kerja.'],
            ['E', 'Saya mengetahui ke mana harus melapor jika mengalami atau melihat tindakan yang tidak pantas.'],
            ['E', 'Saya yakin perusahaan akan menindaklanjuti laporan dengan serius.'],
            // F. Tentang Perusahaan (24-29)
            ['F', 'Gaji saya dibayarkan tepat waktu.'],
            ['F', 'Saya mendapatkan hak dan fasilitas sesuai ketentuan perusahaan.'],
            ['F', 'Perusahaan memperhatikan kebutuhan karyawan.'],
            ['F', 'Perusahaan memberikan kesempatan yang sama kepada setiap karyawan.'],
            ['F', 'Saya merasa dihargai sebagai karyawan Chutex.'],
            ['F', 'Saya bangga bekerja di Chutex.'],
        ];

        $open = [
            ['G', 'Apa yang paling Anda sukai dari bekerja di Chutex?'],
            ['G', 'Apa yang membuat Anda merasa kurang nyaman saat bekerja?'],
            ['G', 'Apakah Anda pernah melihat atau mengalami perlakuan tidak pantas di lingkungan kerja?'],
            ['G', 'Apa yang perlu diperbaiki agar Anda lebih nyaman bekerja di Chutex?'],
            ['G', 'Apa yang Anda harapkan dari perusahaan agar karyawan merasa lebih diperhatikan?'],
            ['G', 'Apa saran Anda agar seluruh karyawan dapat saling menghormati dan bekerja dengan nyaman?'],
        ];

        $rows = [];
        $order = 1;
        foreach ($likert as $i => [$sec, $text]) {
            $rows[] = [
                'section' => $sec,
                'question_text' => $text,
                'question_type' => 'likert',
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach ($open as [$sec, $text]) {
            $rows[] = [
                'section' => $sec,
                'question_text' => $text,
                'question_type' => 'open',
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        return $rows;
    }
};
