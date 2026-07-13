<?php

namespace App\Http\Controllers;

use App\Models\Canteen;
use App\Models\FoodMenu;
use App\Models\FoodOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FoodOrderController extends Controller
{
    /**
     * Halaman pemesanan untuk karyawan.
     * NOTE: sesuaikan `auth()->user()->npk` dengan field NPK
     * yang sebenarnya ada di model User pada sistem hr-cii kamu.
     */
    public function index(Request $request)
    {
        $npk = session('food_order.npk') ?? optional(auth()->user())->npk;
        $isQrMode = !auth()->check() && session()->has('food_order.npk');

        $minDate = Carbon::tomorrow();
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->date)
            : $minDate->copy();

        if ($selectedDate->lt($minDate)) {
            $selectedDate = $minDate->copy();
        }

        $menus = FoodMenu::with('canteen')
            ->where('is_active', true)
            ->get()
            ->filter(fn($menu) => $menu->isAvailableOn($selectedDate))
            ->groupBy(fn($menu) => $menu->canteen->name);

        $myOrder = FoodOrder::with('foodMenu.canteen')
            ->where('npk', $npk)
            ->whereDate('order_date', $selectedDate->toDateString())
            ->first();

        return view('food_order.employee', compact('menus', 'myOrder', 'selectedDate', 'minDate', 'isQrMode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'food_menu_id' => 'required|exists:food_menus,id',
            'order_date'   => 'required|date|after_or_equal:' . Carbon::tomorrow()->toDateString(),
            'notes'        => 'nullable|string|max:255',
        ]);
        $npk = session('food_order.npk') ?? optional(auth()->user())->npk;

        if (!$npk) {
            return back()->with('error', 'Sesi tidak valid, silakan scan ulang QR code.');
        }
        $orderDate = Carbon::parse($request->order_date);
        $menu = FoodMenu::with('canteen')->findOrFail($request->food_menu_id);

        if (!$menu->isAvailableOn($orderDate)) {
            return back()->with('error', 'Menu tidak tersedia untuk tanggal yang dipilih.');
        }

        if (!is_null($menu->quota) && $menu->remainingQuota($orderDate) <= 0) {
            return back()->with('error', 'Kuota menu ini sudah habis untuk tanggal tersebut.');
        }

        $existing = FoodOrder::where('npk', $npk)
            ->whereDate('order_date', $orderDate->toDateString())
            ->first();

        if ($existing) {
            if (!$existing->canBeEdited()) {
                return back()->with('error', 'Pesanan tidak bisa diubah karena sudah masuk hari-H.');
            }

            $existing->update([
                'food_menu_id' => $menu->id,
                'canteen_id'   => $menu->canteen_id,
                'notes'        => $request->notes,
                'status'       => 'pending',
            ]);

            return redirect()->route('food-orders.index', ['date' => $orderDate->toDateString()])
                ->with('success', 'Pesanan berhasil diubah menjadi "' . $menu->name . '".');
        }

        FoodOrder::create([
            'npk'          => $npk,
            'food_menu_id' => $menu->id,
            'canteen_id'   => $menu->canteen_id,
            'order_date'   => $orderDate->toDateString(),
            'status'       => 'pending',
            'notes'        => $request->notes,
        ]);

        return redirect()->route('food-orders.index', ['date' => $orderDate->toDateString()])
            ->with('success', 'Pesanan "' . $menu->name . '" berhasil dibuat.');
    }

    public function destroy($id)
    {
        $order = FoodOrder::where('npk', auth()->user()->npk)->findOrFail($id);

        if (!$order->canBeEdited()) {
            return back()->with('error', 'Pesanan tidak bisa dibatalkan karena sudah masuk hari-H.');
        }

        $orderDate = $order->order_date->toDateString();
        $order->delete();

        return redirect()->route('food-orders.index', ['date' => $orderDate])
            ->with('success', 'Pesanan berhasil dibatalkan.');
    }

    /**
     * Cari kantin yang cocok dengan role user yang sedang login.
     *
     * Role TIDAK disimpan di kolom `users`, melainkan lewat pivot
     * `model_has_roles` -> `roles` (pola Spatie laravel-permission).
     * Nama role (mis. 'Kantin 1', 'Kantin 2') dicocokkan dengan
     * kolom `location` di tabel `canteens` (bukan `name`).
     *
     * Kalau user punya role selain nama kantin (mis. 'Admin', 'HRD'),
     * atau tidak ada role yang cocok dengan kantin manapun, method ini
     * mengembalikan null -> user melihat/memilih semua kantin.
     */
    private function resolveCanteenForCurrentUser($canteens)
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        // model_has_roles.model_id = users.id, dihubungkan ke roles.name
        $roleNames = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->pluck('roles.name');

        if ($roleNames->isEmpty()) {
            return null;
        }

        foreach ($roleNames as $roleName) {
            $match = $canteens->first(function ($canteen) use ($roleName) {
                return $canteen->location
                    && strcasecmp(trim($canteen->location), trim($roleName)) === 0;
            });

            if ($match) {
                return $match;
            }
        }

        // Tidak ada role yang cocok dengan kantin manapun (mis. Admin/HRD) -> lihat semua kantin
        return null;
    }

    /**
     * Rekap untuk pihak catering / admin -> lihat total pesanan per menu & kantin.
     * Jika role user login = nama salah satu kantin, halaman ini otomatis
     * terkunci ke data kantin tersebut saja.
     */
    public function recap()
    {
        $canteens = Canteen::where('is_active', true)->orderBy('name')->get();
        $myCanteen = $this->resolveCanteenForCurrentUser($canteens);

        return view('food_order.recap', compact('canteens', 'myCanteen'));
    }

    public function recapData(Request $request)
    {
        $date = $request->input('date', Carbon::tomorrow()->toDateString());

        $canteens = Canteen::where('is_active', true)->get();
        $myCanteen = $this->resolveCanteenForCurrentUser($canteens);

        // Kalau user terikat ke kantin tertentu lewat role, paksa filter ke
        // kantin itu saja -> abaikan canteen_id yang dikirim client demi keamanan.
        $canteenId = $myCanteen ? $myCanteen->id : $request->input('canteen_id');

        $query = FoodOrder::with(['foodMenu', 'canteen'])
            ->whereDate('order_date', $date)
            ->where('status', '!=', 'cancelled');

        if ($canteenId) {
            $query->where('canteen_id', $canteenId);
        }

        $orders = $query->get();

        $summary = $orders->groupBy('food_menu_id')->map(function ($group) {
            $menu = $group->first()->foodMenu;
            return [
                'menu_name'    => $menu->name,
                'canteen_name' => $group->first()->canteen->name,
                'photo'        => $menu->photo ? asset('storage/' . $menu->photo) : null,
                'total'        => $group->count(),
            ];
        })->values();

        // Breakdown per kantin -> dipakai untuk pie/doughnut chart di rekap.
        // Hanya relevan kalau user melihat lebih dari 1 kantin (tidak terkunci role).
        $byCanteen = $orders->groupBy('canteen_id')->map(function ($group) {
            return [
                'canteen_name' => $group->first()->canteen->name,
                'total'        => $group->count(),
            ];
        })->values();

        $details = $orders->map(fn($o) => [
            'npk'    => $o->npk,
            'menu'   => $o->foodMenu->name,
            'kantin' => $o->canteen->name,
            'status' => $o->status,
        ])->values();

        return response()->json([
            'summary'    => $summary,
            'by_canteen' => $byCanteen,
            'details'    => $details,
            'total'      => $orders->count(),
        ]);
    }

    public function showScan()
    {
        return view('food_order.scan');
    }

    public function verifyScan(Request $request)
    {
        $request->validate(['qr_code' => 'required|string']);

        $raw = trim($request->qr_code);

        if (!preg_match('/^([A-Za-z]-\d{5,})_(.+)$/', $raw, $m)) {
            return response()->json(['success' => false, 'message' => 'Format QR code tidak valid.']);
        }

        [$npk, $namaQr] = [$m[1], $m[2]];


        $employee = DB::connection('cii')->table('BIODATA')
            ->select(
                'NPK',
                DB::raw('LTRIM(RTRIM(NAMA_KARYAWAN)) as NAMA_KARYAWAN')
            )
            ->unionAll(
                DB::connection('cii')->table('BIODATA_KELUAR')
                    ->select(
                        'NPK',
                        DB::raw('LTRIM(RTRIM(NAMA_KARYAWAN)) as NAMA_KARYAWAN')
                    )
            )->where('NPK', $npk)->first();
        // dd($employee);

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan / tidak terdaftar.']);
        }

        if (strcasecmp(trim($employee->NAMA_KARYAWAN), trim($namaQr)) !== 0) {
            return response()->json(['success' => false, 'message' => 'Data QR code tidak sesuai data karyawan.']);
        }

        session([
            'food_order.npk'        => $employee->NPK,
            'food_order.nama'       => $employee->NAMA_KARYAWAN,
            'food_order.scanned_at' => now(),
        ]);

        return response()->json([
            'success'  => true,
            'redirect' => route('food-orders.index'),
        ]);
    }

    public function logoutScan()
    {
        session()->forget(['food_order.npk', 'food_order.nama', 'food_order.scanned_at']);
        return redirect()->route('food-orders.scan')->with('success', 'Sesi keluar, silakan scan ulang.');
    }
}
