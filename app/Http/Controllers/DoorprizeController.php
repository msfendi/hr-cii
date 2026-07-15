<?php

namespace App\Http\Controllers;

use App\Models\DoorprizeScan;
use App\Models\DoorprizeWinner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DoorprizeController extends Controller
{
    /**
     * Halaman scan QR NPK.
     */
    public function scanPage()
    {
        $totalScanned   = DoorprizeScan::count();
        $totalAvailable = DoorprizeScan::available()->count();

        return view('doorprize.scan', compact('totalScanned', 'totalAvailable'));
    }

    /**
     * Endpoint dipanggil setiap kali ZXing berhasil decode QR.
     *
     * Format QR yang didukung:
     *   - "C-00001_NAMA KARYAWAN"  (yang dipakai di ID card)
     *   - "C-00001"                (kalau QR cuma berisi NPK)
     *
     * NPK diekstrak di sisi server (bagian sebelum underscore pertama),
     * supaya format QR bebas berubah tanpa perlu ubah logic di client.
     */
    public function storeScan(Request $request): JsonResponse
    {
        $rawQr = trim((string) $request->input('qr_code', $request->input('npk', '')));

        if ($rawQr === '') {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'QR code tidak terbaca / kosong.',
            ], 422);
        }

        if (!preg_match('/^([A-Za-z]-\d{5})(?:_.*)?$/', strtoupper($rawQr), $matches)) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'Format QR Code tidak valid. Contoh format yang benar: C-00001_NAMA KARYAWAN',
            ], 422);
        }

        $npk = $matches[1];

        // Cek duplikat -> hanya boleh scan 1x
        $existing = DoorprizeScan::where('npk', $npk)->first();
        if ($existing) {
            return response()->json([
                'status'     => 'duplicate',
                'message'    => "NPK {$npk} sudah pernah discan sebelumnya!",
                'scanned_at' => optional($existing->scanned_at)->format('d-m-Y H:i:s'),
            ], 409);
        }

        // Validasi NPK terdaftar di data karyawan (BIODATA / BIODATA_KELUAR)
        $employee = $this->findEmployeeByNpk($npk);
        if (!$employee) {
            return response()->json([
                'status'  => 'not_found',
                'message' => "NPK {$npk} tidak ditemukan di data karyawan.",
            ], 404);
        }

        DoorprizeScan::create([
            'npk'        => $npk,
            'scanned_by' => auth()->id(),
            'scanned_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "NPK {$npk} berhasil discan.",
            'data'    => [
                'npk'             => $npk,
                'name'            => $employee->NAMA_KARYAWAN ?? '-',
                'department'      => $employee->BAG ?? '-',
                'total_scanned'   => DoorprizeScan::count(),
                'total_available' => DoorprizeScan::available()->count(),
            ],
        ]);
    }

    /**
     * Halaman undian doorprize.
     */
    public function drawPage()
    {
        $totalScanned   = DoorprizeScan::count();
        $totalAvailable = DoorprizeScan::available()->count();
        $totalWon       = DoorprizeWinner::active()->count();
        $totalVoid      = DoorprizeWinner::voided()->count();

        $winners = DoorprizeWinner::orderByDesc('won_at')
            ->get()
            ->map(fn($w) => $this->formatWinner($w));

        return view('doorprize.draw', compact(
            'totalScanned',
            'totalAvailable',
            'totalWon',
            'totalVoid',
            'winners'
        ));
    }

    /**
     * Jalankan undian sejumlah $amount pemenang, diambil random dari NPK
     * yang sudah discan dan belum pernah menang.
     */
    public function draw(Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|integer|min:1|max:100',
            'batch_label' => 'nullable|string|max:100',
        ]);

        $amount = (int) $request->amount;
        $availableCount = DoorprizeScan::available()->count();

        if ($availableCount === 0) {
            return response()->json([
                'status'  => 'empty',
                'message' => 'Tidak ada peserta yang tersedia untuk diundi. Pastikan sudah ada NPK yang discan.',
            ], 422);
        }

        if ($amount > $availableCount) {
            return response()->json([
                'status'  => 'insufficient',
                'message' => "Jumlah undian ({$amount}) melebihi peserta yang tersisa ({$availableCount}).",
            ], 422);
        }

        $batchLabel = $request->batch_label ?: ('Undian ' . now()->format('d-m-Y H:i'));

        $results = DB::transaction(function () use ($amount, $batchLabel) {
            // lockForUpdate mencegah 2 request draw yang jalan bersamaan
            // mengambil NPK yang sama.
            $winnerScans = DoorprizeScan::available()
                ->inRandomOrder()
                ->limit($amount)
                ->lockForUpdate()
                ->get();

            $results = [];

            foreach ($winnerScans as $scan) {
                $employee = $this->findEmployeeByNpk($scan->npk);

                $photoPath = $this->resolvePhotoPath(
                    $scan->npk,
                    $employee->NAMA_KARYAWAN ?? null,
                    $employee->DEPARTEMENT ?? null
                );

                $winner = DoorprizeWinner::create([
                    'npk'         => $scan->npk,
                    'name'        => $employee->NAMA_KARYAWAN ?? null,
                    'department'  => $employee->BAG ?? null,
                    'photo'       => $photoPath, // path relatif di disk "public", null kalau belum ada foto
                    'batch_label' => $batchLabel,
                    'drawn_by'    => auth()->id(),
                    'won_at'      => now(),
                ]);

                // Tandai scan ini sudah menang -> tidak akan pernah ikut undian lagi,
                // walaupun nanti pemenangnya dihanguskan (void).
                $scan->update(['is_winner' => true]);

                $results[] = $this->formatWinner($winner);
            }

            return $results;
        });

        return response()->json([
            'status'    => 'success',
            'message'   => count($results) . ' pemenang berhasil diundi.',
            'winners'   => $results,
            'remaining' => DoorprizeScan::available()->count(),
            'total_won' => DoorprizeWinner::active()->count(),
        ]);
    }

    /**
     * Hanguskan satu pemenang. NPK tetap tidak bisa ikut undian lagi
     * (karena doorprize_scans.is_winner tidak direset), hanya status
     * hadiahnya yang jadi tidak berlaku.
     */
    public function voidWinner(Request $request, DoorprizeWinner $winner): JsonResponse
    {
        if ($winner->is_void) {
            return response()->json([
                'status'  => 'already_void',
                'message' => "NPK {$winner->npk} sudah dihanguskan sebelumnya.",
            ], 422);
        }

        $winner->update([
            'is_void'     => true,
            'void_reason' => $request->input('reason'),
            'voided_at'   => now(),
            'voided_by'   => auth()->id(),
        ]);

        return response()->json([
            'status'    => 'success',
            'message'   => "NPK {$winner->npk} berhasil dihanguskan.",
            'winner'    => $this->formatWinner($winner),
            'total_won' => DoorprizeWinner::active()->count(),
            'total_void' => DoorprizeWinner::voided()->count(),
        ]);
    }

    /**
     * List seluruh pemenang (dipakai untuk refresh tabel via AJAX kalau diperlukan).
     */
    public function winnersList(): JsonResponse
    {
        $winners = DoorprizeWinner::orderByDesc('won_at')
            ->get()
            ->map(fn($w) => $this->formatWinner($w));

        return response()->json(['data' => $winners]);
    }

    /**
     * Reset seluruh data scan.
     */
    public function resetScans(): JsonResponse
    {
        DoorprizeScan::truncate();

        return response()->json([
            'status'  => 'success',
            'message' => 'Seluruh data scan berhasil direset.',
        ]);
    }

    /**
     * Reset seluruh data pemenang, dan kembalikan semua NPK yang sudah
     * pernah scan ke pool undian (is_winner = false).
     */
    public function resetWinners(): JsonResponse
    {
        DoorprizeWinner::truncate();
        DoorprizeScan::query()->update(['is_winner' => false]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data pemenang berhasil direset, seluruh NPK dikembalikan ke pool undian.',
        ]);
    }

    /**
     * Bentuk array seragam untuk response JSON & untuk blade view.
     */
    private function formatWinner(DoorprizeWinner $winner): array
    {
        return [
            'id'          => $winner->id,
            'npk'         => $winner->npk,
            'name'        => $winner->name ?: '-',
            'department'  => $winner->department ?: '-',
            'photo'       => $winner->photo ? asset('storage/' . $winner->photo) : $this->defaultPhotoUrl(),
            'batch_label' => $winner->batch_label,
            'won_at'      => optional($winner->won_at)->format('d-m-Y H:i:s'),
            'is_void'     => (bool) $winner->is_void,
            'void_reason' => $winner->void_reason,
            'voided_at'   => optional($winner->voided_at)->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * Cari data karyawan berdasarkan NPK dari BIODATA UNION BIODATA_KELUAR
     * lewat koneksi database "cii", di-join ke tabel DEPT (ID_DEPT) untuk
     * dapat nama department (DEPT.DEPARTEMENT) yang dipakai sebagai nama
     * folder foto profil, misal "HR".
     */
    private function findEmployeeByNpk(string $npk)
    {
        $sql = "
            SELECT EMP.NPK, EMP.NAMA_KARYAWAN, EMP.BAG, EMP.ID_DEPT, EMP.JENIS_KEL,
                   EMP.SECTION, EMP.STATUS, EMP.IS_STAFF, EMP.IS_EXPAT,
                   D.DEPARTEMENT
            FROM (
                SELECT NPK, NAMA_KARYAWAN, BAG, ID_DEPT, JENIS_KEL, SECTION, STATUS, IS_STAFF, IS_EXPAT
                FROM BIODATA WHERE NPK = ?
                UNION
                SELECT NPK, NAMA_KARYAWAN, BAG, ID_DEPT, JENIS_KEL, SECTION, STATUS, IS_STAFF, IS_EXPAT
                FROM BIODATA_KELUAR WHERE NPK = ?
            ) AS EMP
            LEFT JOIN DEPT AS D ON D.ID_DEPT = EMP.ID_DEPT
        ";

        $result = DB::connection('cii')->select($sql, [$npk, $npk]);

        return $result[0] ?? null;
    }

    /**
     * Cari path foto profil karyawan (relatif terhadap disk "public").
     *
     * Aturan penyimpanan foto:
     *   storage/app/public/img/profile/{DEPARTEMENT}/{NPK}_{NAMA_KARYAWAN}.{jpg|jpeg|png}
     *   contoh: img/profile/HR/C-01193_AQILLA RAHMA AMIRUL PUTRI.jpg
     *
     * Mengembalikan null kalau file tidak ditemukan (biar caller bisa
     * fallback ke foto default).
     */
    private function resolvePhotoPath(string $npk, ?string $namaKaryawan, ?string $departemen): ?string
    {
        if (!$departemen || !$namaKaryawan) {
            return null;
        }

        $departemen = trim($departemen);
        $namaKaryawan = trim($namaKaryawan);

        foreach (['jpg', 'jpeg', 'png'] as $ext) {
            $filename = "{$npk}_{$namaKaryawan}.{$ext}";
            $path = "img/profile/{$departemen}/{$filename}";

            if (Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Path foto default kalau karyawan belum punya foto profil.
     * Taruh file di: storage/app/public/img/profile/default.jpg
     */
    private function defaultPhotoUrl(): string
    {
        return asset('storage/img/profile/default.jpg');
    }
}
