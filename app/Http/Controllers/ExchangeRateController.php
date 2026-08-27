<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Services\BiExchangeRateService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function __construct(protected BiExchangeRateService $biExchangeRateService)
    {
    }

    /**
     * Halaman blade: tabel riwayat kurs USD -> IDR + tombol sync.
     * Default rentang: 90 hari terakhir kalau tidak ada filter.
     */
    public function index(Request $request)
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::today()->subDays(90);

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))
            : Carbon::today();

        $rates = ExchangeRate::query()
            ->usdToIdr()
            ->whereDate('rate_date', '>=', $startDate)
            ->whereDate('rate_date', '<=', $endDate)
            ->latestFirst()
            ->get();

        return view('exchange-rate.index', [
            'rates'     => $rates,
            'startDate' => $request->input('start_date', $startDate->format('Y-m-d')),
            'endDate'   => $request->input('end_date', $endDate->format('Y-m-d')),
        ]);
    }

    /**
     * List kurs USD -> IDR dalam format JSON (untuk konsumsi API/AJAX lain).
     */
    public function data(Request $request): JsonResponse
    {
        $query = ExchangeRate::query()->usdToIdr()->latestFirst();

        if ($request->filled('start_date')) {
            $query->whereDate('rate_date', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('rate_date', '<=', $request->date('end_date'));
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 30))
        );
    }

    /**
     * Kurs paling baru yang tersimpan.
     */
    public function today(): JsonResponse
    {
        $rate = ExchangeRate::query()->usdToIdr()->latestFirst()->first();

        return response()->json(['data' => $rate]);
    }

    /**
     * Endpoint untuk tombol "Sync Kurs".
     *
     * Catatan: sumber data (halaman "Kurs Transaksi BI") cuma menampilkan rilis
     * TERBARU yang sedang tayang di BI (bukan API historis per tanggal), jadi
     * endpoint ini selalu mengambil rilis terbaru — bukan rentang tanggal.
     * Jalankan berulang kali (mis. via scheduler harian) untuk membangun histori.
     */
    public function sync(Request $request): JsonResponse
    {
        $result = $this->biExchangeRateService->syncLatest();

        if (! $result) {
            return response()->json([
                'message' => 'Gagal mengambil kurs dari Bank Indonesia. Coba lagi beberapa saat, atau cek log aplikasi (kemungkinan struktur halaman BI berubah).',
            ], 422);
        }

        return response()->json([
            'message' => "Berhasil sync kurs USD -> IDR untuk tanggal {$result->rate_date->format('d/m/Y')}.",
            'data'    => $result,
        ]);
    }
}
