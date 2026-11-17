<?php

namespace App\Http\Controllers;

use App\Models\WhatsNewItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Admin What's New — CRUD banner slider untuk HRIS v1 dan v2.
 *
 * Media disimpan di disk `public` (storage/app/public/whats-new) supaya
 * tidak menumpuk di folder public/ yang ikut ter-commit.
 */
class WhatsNewController extends Controller
{
    public function index()
    {
        $items = WhatsNewItem::orderBy('sort_order')->orderByDesc('id')->get();

        return view('whats_new.index', compact('items'));
    }

    public function create()
    {
        $item = new WhatsNewItem([
            'media_type' => 'image',
            'is_active'  => true,
            'sort_order' => (int) WhatsNewItem::max('sort_order') + 1,
            'starts_at'  => now(),
        ]);

        return view('whats_new.create', [
            'item'         => $item,
            'ruteTersedia' => $this->ruteTersedia(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $item = new WhatsNewItem($data);
        $item->created_by = auth()->id();
        $item->save();

        $this->simpanMedia($request, $item);

        return redirect()->route('admin.whats-new.index')
            ->with('success', 'Banner What\'s New berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $item = WhatsNewItem::findOrFail($id);

        return view('whats_new.edit', [
            'item'         => $item,
            'ruteTersedia' => $this->ruteTersedia(),
        ]);
    }

    /**
     * Nama route GET tanpa parameter — dipakai sebagai saran pada kolom
     * "Route tujuan tombol". Route berparameter dibuang karena tidak bisa
     * dipanggil tanpa argumen.
     *
     * @return array<int, string>
     */
    private function ruteTersedia(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(function ($route) {
                return $route->getName() !== null
                    && in_array('GET', $route->methods(), true)
                    && count($route->parameterNames()) === 0;
            })
            ->map(function ($route) {
                return (string) $route->getName();
            })
            ->reject(function ($nama) {
                // Hanya nama route yang sah (huruf/angka/titik/dash);
                // sebagian route lama punya nama aneh seperti "/".
                return ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-]*$/', $nama)
                    || str_starts_with($nama, 'admin.whats-new')
                    || str_starts_with($nama, 'generated::')
                    || str_starts_with($nama, 'debugbar.')
                    || str_starts_with($nama, 'sanctum.')
                    || str_starts_with($nama, 'horizon.');
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function update(Request $request, $id)
    {
        $item = WhatsNewItem::findOrFail($id);
        $data = $this->validated($request, $item);

        $item->fill($data)->save();
        $this->simpanMedia($request, $item);

        return redirect()->route('admin.whats-new.index')
            ->with('success', 'Banner What\'s New berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $item = WhatsNewItem::findOrFail($id);
        $item->hapusMedia();
        $item->delete();

        return redirect()->route('admin.whats-new.index')
            ->with('success', 'Banner What\'s New berhasil dihapus.');
    }

    /** Aktif/nonaktif cepat dari halaman daftar. */
    public function toggle($id)
    {
        $item = WhatsNewItem::findOrFail($id);
        $item->is_active = ! $item->is_active;
        $item->save();

        return redirect()->route('admin.whats-new.index')
            ->with('success', $item->is_active
                ? 'Banner diaktifkan.'
                : 'Banner dinonaktifkan.');
    }

    /**
     * Sajikan berkas media banner. Dipakai karena proyek ini tidak memakai
     * symlink public/storage, sehingga berkas di storage/app/public tidak
     * otomatis bisa diakses dari browser.
     */
    public function media(string $berkas)
    {
        if (str_contains($berkas, '..') || str_contains($berkas, '/')) {
            abort(404);
        }

        $path = 'whats-new/' . $berkas;

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Aturan validasi dipisah ke method publik agar bisa diuji tanpa
     * menyentuh database.
     */
    public function aturan(Request $request): array
    {
        $tipe = (string) $request->input('media_type', 'image');

        $mimes = $tipe === 'video'
            ? 'mp4,webm,ogg,mov'
            : 'jpg,jpeg,png,webp,gif';

        return [
            'title'        => ['required', 'string', 'max:150'],
            'summary'      => ['nullable', 'string', 'max:1000'],
            'media_type'   => ['required', 'in:image,video,none'],
            'media_file'   => ['nullable', 'file', 'max:51200', 'mimes:' . $mimes],
            'media_url'    => ['nullable', 'url', 'max:500'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_route' => ['nullable', 'string', 'max:150'],
            'button_url'   => ['nullable', 'url', 'max:500'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active'    => ['nullable', 'boolean'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],
            'hapus_media'  => ['nullable', 'boolean'],
        ];
    }

    private function validated(Request $request): array
    {
        $validator = validator($request->all(), $this->aturan($request), [], [
            'title'      => 'judul',
            'summary'    => 'penjelasan singkat',
            'media_type' => 'jenis media',
            'media_file' => 'berkas media',
            'media_url'  => 'URL media',
        ]);

        // Aturan lintas-kolom: dicek setelah validasi dasar lolos.
        $validator->after(function (Validator $v) use ($request) {
            $tipe   = (string) $request->input('media_type', 'image');
            $punya  = $request->hasFile('media_file')
                || trim((string) $request->input('media_url')) !== '';

            if ($tipe !== 'none' && ! $punya && ! $request->boolean('hapus_media')) {
                $v->errors()->add('media_file', 'Isi media (unggah berkas atau tempel URL) untuk jenis media ' . $tipe . '.');
            }

            $label = trim((string) $request->input('button_label'));
            $tujuan = trim((string) $request->input('button_route')) !== ''
                || trim((string) $request->input('button_url')) !== '';

            if ($label !== '' && ! $tujuan) {
                $v->errors()->add('button_route', 'Tombol butuh route atau URL tujuan.');
            }

            if ($label === '' && $tujuan) {
                $v->errors()->add('button_label', 'Isi label tombol (mis. "Lihat detail") atau kosongkan tujuannya.');
            }

            $route = trim((string) $request->input('button_route'));
            if ($route !== '' && ! Route::has($route)) {
                $v->errors()->add('button_route', 'Route "' . $route . '" tidak terdaftar. Periksa nama route di routes/web.php.');
            }
        });

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $data = [
            'title'        => trim((string) $request->input('title')),
            'summary'      => $request->filled('summary') ? trim((string) $request->input('summary')) : null,
            'media_type'   => (string) $request->input('media_type', 'image'),
            'media_url'    => $request->filled('media_url') ? trim((string) $request->input('media_url')) : null,
            'button_label' => $request->filled('button_label') ? trim((string) $request->input('button_label')) : null,
            'button_route' => $request->filled('button_route') ? trim((string) $request->input('button_route')) : null,
            'button_url'   => $request->filled('button_url') ? trim((string) $request->input('button_url')) : null,
            'sort_order'   => (int) $request->input('sort_order', 0),
            'is_active'    => $request->boolean('is_active'),
            'starts_at'    => $request->filled('starts_at') ? $request->input('starts_at') : null,
            'ends_at'      => $request->filled('ends_at') ? $request->input('ends_at') : null,
        ];

        // Media "none": bersihkan referensi agar tidak ada sisa.
        if ($data['media_type'] === 'none') {
            $data['media_url'] = null;
        }

        return $data;
    }

    /** Simpan berkas baru (bila ada) dan hapus berkas lama. */
    private function simpanMedia(Request $request, WhatsNewItem $item): void
    {
        $ganti = false;

        if ($request->hasFile('media_file')) {
            $berkas = $request->file('media_file');
            $nama = date('Ymd-His') . '-' . Str::slug(Str::limit($item->title, 40, '')) . '.' . $berkas->getClientOriginalExtension();

            $item->hapusMedia();
            $item->media_path = $berkas->storeAs('whats-new', $nama, 'public');
            $ganti = true;
        } elseif ($request->boolean('hapus_media') && $item->media_path) {
            $item->hapusMedia();
            $item->media_path = null;
            $ganti = true;
        }

        if ($ganti) {
            $item->save();
        }
    }
}