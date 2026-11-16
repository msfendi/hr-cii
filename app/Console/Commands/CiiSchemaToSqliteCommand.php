<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

/**
 * Mengubah dump skema SQL Server (Navicat "Dump SQL") menjadi skema SQLite
 * yang bisa dipakai sebagai referensi lokal / harness uji tanpa menyentuh
 * database produksi.
 *
 * Yang dikonversi: CREATE TABLE, PRIMARY KEY, FOREIGN KEY, DEFAULT.
 * Yang dilewati: IF EXISTS ... DROP, SET (LOCK_ESCALATION), COLLATE, GO, komentar.
 *
 * Pemakaian:
 *   php artisan cii:schema-to-sqlite storage/app/cii.sql
 *   php artisan cii:schema-to-sqlite storage/app/cii.sql --build=/tmp/cii_reference.sqlite
 */
class CiiSchemaToSqliteCommand extends Command
{
    protected $signature = 'cii:schema-to-sqlite
        {dump : Path file dump SQL Server (Navicat) yang akan dikonversi}
        {--out=database/reference/cii_sqlite_schema.sql : Path keluaran skema SQLite}
        {--build= : Path file SQLite yang dibuat dari skema tersebut (opsional)}
        {--force : Timpa file keluaran bila sudah ada}';

    protected $description = 'Konversi dump skema SQL Server ke skema SQLite (referensi lokal)';

    /** Peta tipe SQL Server -> SQLite. */
    private const TYPE_MAP = [
        'bigint' => 'INTEGER', 'int' => 'INTEGER', 'smallint' => 'INTEGER', 'tinyint' => 'INTEGER',
        'bit' => 'INTEGER',
        'decimal' => 'NUMERIC', 'numeric' => 'NUMERIC', 'money' => 'NUMERIC', 'smallmoney' => 'NUMERIC',
        'float' => 'REAL', 'real' => 'REAL',
        'datetime' => 'TEXT', 'datetime2' => 'TEXT', 'smalldatetime' => 'TEXT', 'datetimeoffset' => 'TEXT',
        'date' => 'TEXT', 'time' => 'TEXT', 'timestamp' => 'TEXT',
        'char' => 'TEXT', 'varchar' => 'TEXT', 'nchar' => 'TEXT', 'nvarchar' => 'TEXT',
        'text' => 'TEXT', 'ntext' => 'TEXT', 'sysname' => 'TEXT', 'xml' => 'TEXT',
        'uniqueidentifier' => 'TEXT', 'rowversion' => 'BLOB',
        'binary' => 'BLOB', 'varbinary' => 'BLOB', 'image' => 'BLOB',
    ];

    public function handle(): int
    {
        $dump = $this->argument('dump');
        if (! is_file($dump)) {
            $this->error("File dump tidak ditemukan: {$dump}");

            return self::FAILURE;
        }

        $raw = $this->bersihkan(file_get_contents($dump));
        $statements = preg_split('/^\s*GO\s*$/mi', $raw) ?: [];

        $tabel = [];       // name => [ 'columns' => [...], 'pk' => [...], 'fks' => [...] ]
        $tipeTakDikenal = [];
        $diabaikan = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            if (preg_match('/^CREATE\s+TABLE\s+\[(?:dbo)\]\.\[([^\]]+)\]/i', $statement, $m)) {
                $nama = $m[1];
                $body = $this->ambilBody($statement);
                $tabel[$nama] = ['columns' => [], 'pk' => [], 'fks' => []];

                foreach ($this->pecahKolom($body) as $definisi) {
                    $kolom = $this->parseKolom($definisi, $tipeTakDikenal);
                    if ($kolom !== null) {
                        $tabel[$nama]['columns'][] = $kolom;
                    }
                }
                continue;
            }

            if (preg_match('/^ALTER\s+TABLE\s+\[dbo\]\.\[([^\]]+)\]\s+ADD\s+CONSTRAINT\s+\[([^\]]+)\]\s+PRIMARY\s+KEY\s+(?:CLUSTERED|NONCLUSTERED)?\s*\(([^)]*)\)/i', $statement, $m)) {
                $tabel[$m[1]]['pk'] = $this->daftarKolom($m[3]);
                continue;
            }

            if (preg_match('/^ALTER\s+TABLE\s+\[dbo\]\.\[([^\]]+)\]\s+ADD\s+CONSTRAINT\s+\[([^\]]+)\]\s+FOREIGN\s+KEY\s*\(([^)]*)\)\s*REFERENCES\s+\[dbo\]\.\[([^\]]+)\]\s*\(([^)]*)\)(.*)$/is', $statement, $m)) {
                $aksi = strtoupper(trim($m[6]));
                $onDelete = preg_match('/ON\s+DELETE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|RESTRICT|NO\s+ACTION)/i', $aksi, $od) ? strtoupper(preg_replace('/\s+/', ' ', $od[1])) : null;
                $tabel[$m[1]]['fks'][] = [
                    'name' => $m[2],
                    'columns' => $this->daftarKolom($m[3]),
                    'ref_table' => $m[4],
                    'ref_columns' => $this->daftarKolom($m[5]),
                    'on_delete' => $onDelete,
                ];
                continue;
            }

            $diabaikan++;
        }

        $sql = $this->buatSql($tabel);

        $out = $this->option('out');
        if (is_file($out) && ! $this->option('force')) {
            $this->error("File keluaran sudah ada: {$out} (pakai --force untuk menimpa)");

            return self::FAILURE;
        }
        @mkdir(dirname($out), 0775, true);
        file_put_contents($out, $sql);

        $jumlahKolom = array_sum(array_map(fn ($t) => count($t['columns']), $tabel));
        $jumlahPk = count(array_filter($tabel, fn ($t) => $t['pk'] !== []));
        $jumlahFk = array_sum(array_map(fn ($t) => count($t['fks']), $tabel));

        $this->info('Skema SQLite dibuat: '.$out);
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Tabel', count($tabel)],
                ['Kolom', $jumlahKolom],
                ['Primary key', $jumlahPk],
                ['Foreign key', $jumlahFk],
                ['Pernyataan dilewati', $diabaikan],
                ['Baris skema', substr_count($sql, "\n")],
            ]
        );

        if ($tipeTakDikenal !== []) {
            $this->warn('Tipe tidak dikenal (dipetakan ke TEXT): '.implode(', ', array_unique($tipeTakDikenal)));
        }

        if ($build = $this->option('build')) {
            if (! $this->bangunSqlite($build, $tabel)) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /** Buang BOM, samakan line ending, dan hapus komentar blok serta komentar baris. */
    private function bersihkan(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $raw = preg_replace('#/\*.*?\*/#s', '', $raw) ?? $raw;

        return preg_replace('/^\s*--.*$/m', '', $raw) ?? $raw;
    }

    /** Ambil isi tanda kurung terluar setelah nama tabel. */
    private function ambilBody(string $statement): string
    {
        $start = strpos($statement, '(');
        if ($start === false) {
            return '';
        }

        $depth = 0;
        $len = strlen($statement);
        for ($i = $start; $i < $len; $i++) {
            $c = $statement[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($statement, $start + 1, $i - $start - 1);
                }
            }
        }

        return substr($statement, $start + 1);
    }

    /** Pecah definisi kolom pada koma di luar tanda kurung, string, dan kurung siku. */
    private function pecahKolom(string $body): array
    {
        $hasil = [];
        $buffer = '';
        $depth = 0;
        $quote = false;
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];

            if ($quote) {
                $buffer .= $c;
                if ($c === "'") {
                    $quote = false;
                }
                continue;
            }

            if ($c === "'") {
                $quote = true;
                $buffer .= $c;
                continue;
            }

            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            }

            if ($c === ',' && $depth === 0) {
                $hasil[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $c;
        }

        if (trim($buffer) !== '') {
            $hasil[] = $buffer;
        }

        return array_values(array_filter($hasil, fn ($d) => trim($d) !== ''));
    }

    /** Ubah satu definisi kolom menjadi struktur internal. */
    private function parseKolom(string $definisi, array &$tipeTakDikenal): ?array
    {
        if (! preg_match('/^\[([^\]]+)\]\s+([A-Za-z0-9_]+)\s*(\(([^)]*)\))?\s*(.*)$/is', trim($definisi), $m)) {
            return null;
        }

        $nama = $m[1];
        $tipeSql = strtolower($m[2]);
        $sisa = trim($m[5] ?? '');

        $tipe = self::TYPE_MAP[$tipeSql] ?? null;
        if ($tipe === null) {
            $tipe = 'TEXT';
            $tipeTakDikenal[] = $tipeSql;
        }

        $notNull = (bool) preg_match('/\bNOT\s+NULL\b/i', $sisa);
        $identity = (bool) preg_match('/\bIDENTITY\b/i', $sisa);

        $default = null;
        if (preg_match('/\bDEFAULT\s+(.+?)(?=\s+(?:NOT\s+NULL|NULL|COLLATE|IDENTITY|ROWGUIDCOL|SPARSE)\b|$)/is', $sisa, $dm)) {
            $default = $this->normalkanDefault(trim($dm[1]), $tipe);
        }

        return [
            'name' => $nama,
            'type' => $tipe,
            'not_null' => $notNull,
            'identity' => $identity,
            'default' => $default,
        ];
    }

    /** Terjemahkan DEFAULT SQL Server ke bentuk SQLite. */
    private function normalkanDefault(string $nilai, string $tipe): string
    {
        $nilai = trim($nilai, " \t\n()");
        $bawah = strtolower($nilai);

        $peta = [
            'getdate()' => 'CURRENT_TIMESTAMP',
            'sysdatetime()' => 'CURRENT_TIMESTAMP',
            'getutcdate()' => 'CURRENT_TIMESTAMP',
            'sysutcdatetime()' => 'CURRENT_TIMESTAMP',
            'newid()' => "(lower(hex(randomblob(16))))",
            'newsequentialid()' => "(lower(hex(randomblob(16))))",
        ];
        if (isset($peta[$bawah])) {
            return $peta[$bawah];
        }

        if ($bawah === 'null') {
            return 'NULL';
        }

        // DEFAULT '0' / N'0' pada kolom angka -> 0
        if (in_array($tipe, ['INTEGER', 'NUMERIC', 'REAL'], true) && preg_match("/^N?'(-?\d+(?:\.\d+)?)'$/", $nilai, $nm)) {
            return $nm[1];
        }

        if (preg_match("/^N?'(.*)'$/s", $nilai, $nm)) {
            return "'".str_replace("'", "''", $nm[1])."'";
        }

        return $nilai;
    }

    /** Ekstrak daftar nama kolom dari "([a] ASC, [b] DESC)". */
    private function daftarKolom(string $teks): array
    {
        preg_match_all('/\[([^\]]+)\]/', $teks, $m);

        return $m[1] ?? [];
    }

    /** Susun DDL SQLite dari struktur tabel. */
    private function buatSql(array $tabel): string
    {
        $baris = [
            '-- Skema referensi SQLite hasil konversi dari dump skema SQL Server (database CII).',
            '-- Dibuat oleh: php artisan cii:schema-to-sqlite',
            '-- Tujuan: referensi lokal / harness uji. Bukan pengganti database produksi.',
            '',
        ];

        foreach ($tabel as $nama => $info) {
            if ($info['columns'] === []) {
                continue;
            }

            $kolomSql = [];
            foreach ($info['columns'] as $kolom) {
                $potongan = '  "'.$kolom['name'].'" '.$kolom['type'];
                if ($kolom['not_null']) {
                    $potongan .= ' NOT NULL';
                }
                if ($kolom['default'] !== null) {
                    $potongan .= ' DEFAULT '.$kolom['default'];
                }
                $kolomSql[] = $potongan;
            }

            if ($info['pk'] !== []) {
                $kolomSql[] = '  PRIMARY KEY ('.$this->kutip($info['pk']).')';
            }

            foreach ($info['fks'] as $fk) {
                $barisFk = '  CONSTRAINT "'.$fk['name'].'" FOREIGN KEY ('.$this->kutip($fk['columns']).')'
                    .' REFERENCES "'.$fk['ref_table'].'" ('.$this->kutip($fk['ref_columns']).')';
                if ($fk['on_delete'] !== null && $fk['on_delete'] !== 'NO ACTION') {
                    $barisFk .= ' ON DELETE '.$fk['on_delete'];
                }
                $kolomSql[] = $barisFk;
            }

            $baris[] = 'CREATE TABLE "'.$nama.'" (';
            $baris[] = implode(",\n", $kolomSql);
            $baris[] = ');';
            $baris[] = '';
        }

        return implode("\n", $baris)."\n";
    }

    private function kutip(array $kolom): string
    {
        return implode(', ', array_map(fn ($k) => '"'.$k.'"', $kolom));
    }

    /** Buat file SQLite nyata dari struktur tabel. */
    private function bangunSqlite(string $path, array $tabel): bool
    {
        if (is_file($path)) {
            @unlink($path);
        }
        @mkdir(dirname($path), 0775, true);

        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = OFF');

        $gagal = [];
        $pdo->beginTransaction();

        foreach ($tabel as $nama => $info) {
            if ($info['columns'] === []) {
                continue;
            }

            $kolomSql = [];
            foreach ($info['columns'] as $kolom) {
                $potongan = '"'.$kolom['name'].'" '.$kolom['type'];
                if ($kolom['not_null']) {
                    $potongan .= ' NOT NULL';
                }
                if ($kolom['default'] !== null) {
                    $potongan .= ' DEFAULT '.$kolom['default'];
                }
                $kolomSql[] = $potongan;
            }
            if ($info['pk'] !== []) {
                $kolomSql[] = 'PRIMARY KEY ('.$this->kutip($info['pk']).')';
            }
            foreach ($info['fks'] as $fk) {
                $kolomSql[] = 'CONSTRAINT "'.$fk['name'].'" FOREIGN KEY ('.$this->kutip($fk['columns']).')'
                    .' REFERENCES "'.$fk['ref_table'].'" ('.$this->kutip($fk['ref_columns']).')';
            }

            $ddl = 'CREATE TABLE "'.$nama.'" ('.implode(', ', $kolomSql).')';
            try {
                $pdo->exec($ddl);
            } catch (\Throwable $e) {
                $gagal[$nama] = $e->getMessage();
            }
        }

        $pdo->commit();
        $pdo->exec('PRAGMA foreign_keys = ON');

        $cek = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
        $this->info("SQLite dibangun: {$path} ({$cek} tabel)");

        if ($gagal !== []) {
            foreach ($gagal as $nama => $pesan) {
                $this->error("Tabel gagal: {$nama} — {$pesan}");
            }

            return false;
        }

        return true;
    }
}
