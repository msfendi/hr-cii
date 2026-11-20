<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pembaca dokumen aturan RAG (docs/RAG_RULES_CII.md).
 *
 * Aturan main: chatbot WAJIB membaca dokumen ini lebih dulu, baru menyusun
 * query. Service ini yang menyiapkan isi dokumen untuk prompt — bukan
 * menyalin aturan ke dalam kode, supaya aturan bisa diperbarui tim tanpa
 * deploy ulang (cukup edit markdown-nya).
 *
 * Yang dikirim ke prompt hanya BAGIAN YANG RELEVAN (bukan seluruh 4.600
 * baris): ringkasan + R1–R31 + tabel terbatas + batasan SQL Server + format
 * jawaban, ditambah baris peta keputusan yang cocok dengan pertanyaan user.
 * Daftar 167 tabel tidak dikirim dari sini karena sudah dikirim lewat
 * introspeksi skema langsung dari database (lebih akurat).
 */
class RagRules
{
    /** @var array{teks:string,bagian:array<string,string>,sidik:array<string,mixed>}|null */
    protected ?array $dokumen = null;

    public function path(): string
    {
        return (string) config('chatbot.rag_rules_path', base_path('docs/RAG_RULES_CII.md'));
    }

    public function pathRelatif(): string
    {
        $path = $this->path();
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * Muat & urai dokumen. Dilempar RuntimeException kalau berkas tidak ada —
     * pemanggil yang memutuskan mau fail-closed atau lanjut dengan peringatan.
     *
     * @return array{teks:string,bagian:array<string,string>,sidik:array<string,mixed>}
     */
    public function muat(): array
    {
        if ($this->dokumen !== null) {
            return $this->dokumen;
        }

        $path = $this->path();

        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('Dokumen aturan RAG tidak ditemukan atau tidak bisa dibaca: ' . $path);
        }

        $mtime = (int) @filemtime($path);
        $size = (int) @filesize($path);
        $cacheKey = 'rag_rules:' . md5($path) . ':' . $mtime . ':' . $size;

        // Cache isi berkas 12 jam; kunci memuat mtime+size sehingga edit
        // dokumen langsung terpakai tanpa perlu cache:clear.
        $teks = Cache::remember($cacheKey, now()->addHours(12), function () use ($path) {
            $isi = @file_get_contents($path);

            if ($isi === false) {
                throw new \RuntimeException('Gagal membaca dokumen aturan RAG: ' . $path);
            }

            return $isi;
        });

        $teks = str_replace(["\r\n", "\r"], "\n", (string) $teks);

        $this->dokumen = [
            'teks' => $teks,
            'bagian' => $this->uraiBagian($teks),
            'sidik' => [
                'berkas' => $this->pathRelatif(),
                'sha256' => substr(hash('sha256', $teks), 0, 16),
                'aturan' => preg_match_all('/^### R\d+\./m', $teks),
                'tabel' => preg_match_all('/^#### `/m', $teks),
                'baris' => substr_count($teks, "\n") + 1,
                'diperbarui' => $mtime > 0 ? date('Y-m-d H:i', $mtime) : null,
            ],
        ];

        return $this->dokumen;
    }

    /**
     * Pecah dokumen per bagian "## 0.", "## 1." dst.
     *
     * @return array<string,string>
     */
    protected function uraiBagian(string $teks): array
    {
        $hasil = [];
        $nomor = null;
        $buffer = [];

        foreach (explode("\n", $teks) as $baris) {
            if (preg_match('/^##\s+(\d+)\./', $baris, $m)) {
                if ($nomor !== null) {
                    $hasil[$nomor] = trim(implode("\n", $buffer));
                }
                $nomor = $m[1];
                $buffer = [$baris];

                continue;
            }

            if ($nomor !== null) {
                $buffer[] = $baris;
            }
        }

        if ($nomor !== null) {
            $hasil[$nomor] = trim(implode("\n", $buffer));
        }

        return $hasil;
    }

    public function bagian(string $nomor): string
    {
        return $this->muat()['bagian'][$nomor] ?? '';
    }

    /**
     * Sidik dokumen untuk ditampilkan di meta jawaban (bukti aturan dibaca).
     *
     * @return array<string,mixed>
     */
    public function sidik(): array
    {
        return $this->muat()['sidik'];
    }

    /**
     * Susun blok aturan untuk prompt SQL: inti aturan + baris peta
     * keputusan yang relevan dengan pertanyaan user.
     */
    public function ringkasanPrompt(?string $pertanyaan = null): string
    {
        $dok = $this->muat();
        $sidik = $dok['sidik'];

        $inti = [];
        foreach (['0', '1'] as $nomor) {
            if (! empty($dok['bagian'][$nomor])) {
                $inti[] = $dok['bagian'][$nomor];
            }
        }

        $blok = "=== ATURAN RAG RESMI (SUMBER KEBENARAN) ===\n"
            . "Berkas: {$sidik['berkas']} · sha256: {$sidik['sha256']} · aturan: {$sidik['aturan']} · "
            . "tabel terdokumentasi: {$sidik['tabel']} · diperbarui: " . ($sidik['diperbarui'] ?? '-') . "\n"
            . "Dokumen ini WAJIB dipatuhi. Kalau ada pertentangan dengan kebiasaan umum penulisan SQL, "
            . "aturan di dokumen ini yang menang.\n\n"
            . implode("\n\n", $inti);

        // Baris peta keputusan diletakkan SEBELUM bagian 6–8 supaya kalau
        // blok dipotong karena batas karakter, panduan yang paling spesifik
        // untuk pertanyaan ini tidak ikut terbuang.
        $relevan = $this->petaRelevan((string) $pertanyaan);

        if ($relevan !== '') {
            $blok .= "\n\n=== BARIS PETA KEPUTUSAN YANG COCOK DENGAN PERTANYAAN INI ===\n"
                . "Pakai tabel/kolom/join persis seperti di bawah (sudah diverifikasi):\n"
                . $relevan;
        }

        // Bagian 7 (batasan SQL Server) dan Bagian 8 (format jawaban) WAJIB ikut
        // SEBELUM Bagian 6. Sebelumnya urutannya 6 → 7 → 8, dan begitu isi
        // Bagian 1 bertambah (R26–R30), pemotongan jatuh di tengah Bagian 6
        // sehingga Bagian 7–8 hilang tanpa jejak. Bagian 6 hanyalah tabel
        // rujukan tabel yang menolak SELECT * — larangan itu tetap ditegakkan
        // di runtime lewat blockedTables()/blockedColumns().
        $wajib = [];
        foreach (['7', '8'] as $nomor) {
            if (! empty($dok['bagian'][$nomor])) {
                $wajib[] = $dok['bagian'][$nomor];
            }
        }

        // Bagian 7 (batasan SQL Server) dan Bagian 8 (format jawaban) adalah
        // bagian yang TIDAK BOLEH hilang. Sebelumnya blok utuh dipotong dari
        // ekor setelah 7–8 ditambahkan, sehingga begitu Bagian 1 bertambah
        // (R26–R31) justru format jawaban yang terbuang. Sekarang ruang 7–8
        // dipesan lebih dulu: yang dipotong adalah inti aturan (0+1+peta).
        $maks = (int) config('chatbot.rag_rules_max_chars', 24000);
        $wajibTeks = implode("\n\n", $wajib);
        $penanda = "\n\n[... aturan dipotong; Bagian 7–8 tetap utuh, lihat dokumen asli untuk sisanya ...]";
        $anggaranInti = $maks - mb_strlen($wajibTeks) - mb_strlen($penanda);

        if ($anggaranInti > 0 && mb_strlen($blok) > $anggaranInti) {
            $potongan = mb_substr($blok, 0, $anggaranInti);
            $akhirBaris = strrpos($potongan, "\n");

            if ($akhirBaris !== false && $akhirBaris > 0) {
                $potongan = substr($potongan, 0, $akhirBaris);
            }

            $blok = $potongan . $penanda;
        }

        if ($wajibTeks !== '') {
            $blok .= "\n\n" . $wajibTeks;
        }

        // Bagian 6 diisi dengan SISA anggaran karakter saja.
        $sisa = $maks - mb_strlen($blok);
        $rujukan = (string) ($dok['bagian']['6'] ?? '');

        if ($rujukan !== '' && $sisa > 400) {
            $batas = $sisa - 80;
            $terpotong = mb_strlen($rujukan) > $batas;
            $potongan = $terpotong ? mb_substr($rujukan, 0, $batas) : $rujukan;

            if ($terpotong) {
                // Potong di batas baris supaya prompt tidak berakhir di tengah kalimat.
                $akhirBaris = strrpos($potongan, "\n");

                if ($akhirBaris !== false) {
                    $potongan = substr($potongan, 0, $akhirBaris);
                }
            }

            $blok .= "\n\n" . $potongan;

            // Penanda HANYA kalau benar-benar dipotong. Sebelumnya penanda
            // selalu ditulis, sehingga prompt mengaku "Bagian 6 dipotong"
            // padahal seluruh rujukan masuk — model lalu mengira daftar
            // tabelnya tidak lengkap dan menebak nama tabel.
            if ($terpotong) {
                $blok .= "\n[... Bagian 6 dipotong; larangan tetap berlaku, lihat dokumen asli ...]";
            }
        }

        return $blok;
    }

    /**
     * Ambil baris tabel Bagian 2 yang paling relevan dengan pertanyaan.
     * Pencocokan sederhana berbasis kata kunci — cukup untuk memilih 3–4
     * baris panduan, bukan untuk menjawab.
     */
    protected function petaRelevan(string $pertanyaan): string
    {
        $peta = $this->bagian('2');

        if ($peta === '' || trim($pertanyaan) === '') {
            return '';
        }

        $kataTanya = $this->kataKunci($pertanyaan);
        $kandidat = [];

        foreach (explode("\n", $peta) as $baris) {
            $baris = trim($baris);

            if (! str_starts_with($baris, '|') || str_contains($baris, '---')) {
                continue;
            }

            $sel = array_values(array_filter(array_map('trim', explode('|', trim($baris, '|'))), fn($v) => $v !== ''));

            if (count($sel) < 2 || mb_strtolower($sel[0]) === 'pertanyaan / kebutuhan') {
                continue;
            }

            $teksBaris = mb_strtolower($baris);
            $skor = 0;

            foreach ($kataTanya as $kata) {
                if (str_contains($teksBaris, $kata)) {
                    $skor += mb_strlen($kata) >= 7 ? 2 : 1;
                }
            }

            if ($skor > 0) {
                $kandidat[] = ['skor' => $skor, 'baris' => $baris];
            }
        }

        if (empty($kandidat)) {
            return '';
        }

        usort($kandidat, fn($a, $b) => $b['skor'] <=> $a['skor']);
        $pilih = array_slice($kandidat, 0, 4);

        $header = '| Pertanyaan / kebutuhan | Tabel utama | Kolom filter | Join | Catatan |'
            . "\n|---|---|---|---|---|";

        return $header . "\n" . implode("\n", array_column($pilih, 'baris'));
    }

    /** @return array<int,string> */
    protected function kataKunci(string $teks): array
    {
        $teks = mb_strtolower($teks);
        $teks = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $teks) ?? $teks;
        $kata = preg_split('/\s+/u', trim($teks)) ?: [];

        // Buang kata umum Indonesia/Inggris yang tidak membedakan topik.
        $stop = [
            'yang', 'dan', 'atau', 'untuk', 'dari', 'dengan', 'pada', 'dalam', 'adalah', 'ada',
            'berapa', 'siapa', 'apa', 'kapan', 'dimana', 'bagaimana', 'apakah', 'tolong', 'coba',
            'tampilkan', 'lihat', 'minta', 'semua', 'data', 'daftar', 'per', 'ini', 'itu',
            'karyawan', 'the', 'and', 'show', 'list', 'all', 'data', 'please',
        ];

        $kata = array_filter($kata, fn($k) => mb_strlen($k) >= 3 && ! in_array($k, $stop, true));

        return array_values(array_unique($kata));
    }

    /**
     * Blocklist teknis dari Bagian 6c dokumen (R32): tabel yang dilarang
     * total dan kolom sensitif per tabel. Ini SUMBER UTAMA blocklist mode
     * RAG — sebelumnya daftar ini hanya hidup di .env
     * (CHATBOT_RAG_BLOCKED_TABLES / CHATBOT_RAG_BLOCKED_COLUMNS) sehingga
     * aturan tidak ikut terdokumentasi dan bisa berbeda antar server.
     *
     * Format yang dibaca, satu baris satu entri, di dalam blok ```blokir-teknis:
     *   TABEL: payroll_masters, payroll_run_details
     *   KOLOM: employees_contract:salary,allowance
     *
     * Kalau bagian itu tidak terbaca atau kosong, dipakai daftar cadangan
     * yang sama (fail-safe: larangan tetap jalan, bukan berarti semua boleh).
     *
     * @return array{tabel:array<int,string>,kolom:array<string,array<int,string>>}
     */
    public function blokTeknis(): array
    {
        $cadangan = [
            // Hanya kredensial/token (R32 versi 18 Nov 2026). Kolom gaji tidak
            // lagi diblokir teknis — aksesnya dijaga cakupan role (R33).
            'tabel' => [],
            'kolom' => [
                'leave_requests' => ['token'],
                'whatsapp_devices' => ['token'],
                'users' => ['password', 'email', 'remember_token', 'two_factor_secret'],
            ],
        ];

        try {
            $bagian = $this->bagian('6');
        } catch (\Throwable $e) {
            Log::warning('RagRules: gagal membaca Bagian 6c (blocklist teknis) — memakai daftar cadangan: ' . $e->getMessage());

            return $cadangan;
        }

        $pos = mb_strpos($bagian, '6c.');

        if ($bagian === '' || $pos === false) {
            Log::warning('RagRules: Bagian 6c tidak ditemukan di dokumen aturan — memakai daftar cadangan blocklist.');

            return $cadangan;
        }

        $tabel = [];
        $kolom = [];

        foreach (explode("\n", mb_substr($bagian, $pos)) as $baris) {
            $baris = trim($baris);

            if (preg_match('/^TABEL:\s*(.+)$/i', $baris, $m)) {
                foreach (explode(',', $m[1]) as $nama) {
                    $nama = strtolower(trim($nama));

                    if ($nama !== '') {
                        $tabel[] = $nama;
                    }
                }

                continue;
            }

            if (preg_match('/^KOLOM:\s*([A-Za-z0-9_]+)\s*:\s*(.+)$/i', $baris, $m)) {
                $namaTabel = strtolower(trim($m[1]));
                $daftar = array_values(array_filter(array_map(
                    fn($c) => strtolower(trim($c)),
                    explode(',', $m[2])
                )));

                if ($namaTabel !== '' && $daftar !== []) {
                    $kolom[$namaTabel] = array_values(array_unique(array_merge($kolom[$namaTabel] ?? [], $daftar)));
                }
            }
        }

        if ($tabel === [] && $kolom === []) {
            Log::warning('RagRules: Bagian 6c ada tetapi tidak berisi baris TABEL:/KOLOM: — memakai daftar cadangan blocklist.');

            return $cadangan;
        }

        return ['tabel' => array_values(array_unique($tabel)), 'kolom' => $kolom];
    }

    /**
     * Empat tabel yang DILARANG dibaca menurut R25 (Bagian 6b). Dipakai untuk
     * memperkuat blocklist runtime, bukan menggantikan konfigurasi.
     *
     * @return array<int,string>
     */
    public function tabelTerlarangR25(): array
    {
        try {
            $bagian = $this->bagian('6');
        } catch (\Throwable $e) {
            Log::warning('RagRules: gagal membaca Bagian 6 (tabel terbatas): ' . $e->getMessage());

            return [];
        }

        if ($bagian === '') {
            return [];
        }

        // Hanya bagian 6b (tabel yang benar-benar dilarang); bagian 6 berisi
        // 24 tabel yang masih boleh dibaca dengan kolom eksplisit.
        $potong = $bagian;
        if (($pos = mb_strpos($bagian, '6b.')) !== false) {
            $potong = mb_substr($bagian, $pos);
        } else {
            return [];
        }

        $hasil = [];
        foreach (explode("\n", $potong) as $baris) {
            if (preg_match('/^\|\s*`([A-Za-z0-9_]+)`\s*\|/', trim($baris), $m)) {
                $hasil[] = $m[1];
            }
        }

        return array_values(array_unique($hasil));
    }
}