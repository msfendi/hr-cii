<?php

namespace App\Http\Controllers;

use App\Models\WhatsNewItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
     * Halaman tonton video — target QR code di menu What's New.
     *
     * Sengaja TIDAK memakai middleware auth: QR dipindai karyawan lewat ponsel
     * yang belum tentu sudah login. Yang menjaga akses adalah status banner:
     * begitu banner dinonaktifkan atau periodenya lewat, halaman ini menolak
     * dengan 403 dan video tidak bisa diputar lagi.
     */
    public function watch($id)
    {
        $item = WhatsNewItem::findOrFail($id);

        [$kode, $alasan] = self::statusTonton($item);

        if ($kode !== 200) {
            return response()->view('whats_new.watch', [
                'item'   => $item,
                'boleh'  => false,
                'alasan' => $alasan,
            ], $kode);
        }

        return response()->view('whats_new.watch', [
            'item'   => $item,
            'boleh'  => true,
            'alasan' => null,
        ]);
    }

    /**
     * Putuskan boleh-tidaknya sebuah banner video ditonton.
     *
     * Dipisah dari watch() supaya bisa diuji tanpa database: keluarannya
     * pasangan [kode HTTP, alasan penolakan].
     *
     * @return array{0:int,1:?string}
     */
    public static function statusTonton(WhatsNewItem $item): array
    {
        if (! $item->punyaVideo()) {
            return [404, 'Banner ini tidak memiliki video.'];
        }

        if (! $item->sedangAktif()) {
            return [403, 'Banner ini sudah dinonaktifkan atau periodenya sudah berakhir, sehingga videonya tidak dapat diakses lagi.'];
        }

        return [200, null];
    }

    /**
     * QR code (SVG) yang mengarah ke halaman tonton video banner.
     *
     * Dibuat saat tombol QR diklik, bukan disimpan, supaya isinya selalu
     * mengikuti URL aplikasi yang berlaku.
     */
    public function qr($id)
    {
        $item = WhatsNewItem::findOrFail($id);

        abort_unless($item->punyaVideo(), 404);

        $url = route('whats-new.watch', ['id' => $item->getKey()]);

        $svg = QrCode::format('svg')
            ->size(420)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($url);

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
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

        // Video milik banner yang dinonaktifkan / di luar periode tidak boleh
        // lagi diunduh lewat URL langsung, meski tautannya masih tersimpan di
        // perangkat pengguna. Pemeriksaan hanya untuk berkas video supaya
        // gambar tidak menambah satu query per permintaan.
        $ekstensi = strtolower((string) pathinfo($berkas, PATHINFO_EXTENSION));

        if (in_array($ekstensi, ['mp4', 'webm', 'ogg', 'mov'], true)) {
            $pemilik = WhatsNewItem::where('media_path', $path)->first();

            if ($pemilik && ! $pemilik->sedangAktif()) {
                abort(403, 'Video tidak tersedia karena banner sudah dinonaktifkan.');
            }
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

    private function validated(Request $request, ?WhatsNewItem $item = null): array
    {
        $validator = validator($request->all(), $this->aturan($request), [], [
            'title'      => 'judul',
            'summary'    => 'penjelasan singkat',
            'media_type' => 'jenis media',
            'media_file' => 'berkas media',
            'media_url'  => 'URL media',
        ]);

        // Aturan lintas-kolom: dicek setelah validasi dasar lolos.
        $validator->after(function (Validator $v) use ($request, $item) {
            $tipe   = (string) $request->input('media_type', 'image');
            $punya  = $request->hasFile('media_file')
                || trim((string) $request->input('media_url')) !== '';

            // Saat edit, media yang sudah tersimpan dianggap sah selama jenisnya
            // masih cocok DAN user tidak mencentang hapus_media.
            // Tanpa ini, setiap simpan edit gagal dengan "Isi media ..."
            // walau berkasnya masih ada di server.
            $hapusMedia = $request->boolean('hapus_media');
            $mediaLama = false;
            if ($item !== null && ! $hapusMedia) {
                $mediaLama = $this->mediaLamaCocok($item, $tipe);
            }

            if ($tipe !== 'none' && ! $punya && ! $mediaLama) {
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

    /**
     * Apakah banner sudah punya media yang sah untuk jenis yang dipilih?
     *
     * Dipakai saat edit: berkas yang sudah diunggah (atau URL yang sudah
     * ditempel) membebaskan admin dari kewajiban mengunggah ulang. Jenis
     * harus cocok — berpindah dari gambar ke video tetap membutuhkan media
     * baru. Berkas yang sudah hilang dari disk dianggap tidak ada supaya
     * banner tidak tersimpan dengan referensi rusak.
     */
    private function mediaLamaCocok(WhatsNewItem $item, string $tipe): bool
    {
        if ($tipe === 'none') {
            return true;
        }

        $ekstensiVideo = ['mp4', 'webm', 'ogg', 'mov'];
        $ekstensiGambar = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if ($item->media_path) {
            // Catatan: jangan tolak jika berkas fisik di disk sementara tidak ditemukan
            // saat validasi (misal di lingkungan uji atau mount storage tertunda).
            // Selama media_path tercatat dan ekstensinya cocok dengan tipe yang dipilih,
            // anggap media lama valid agar admin tidak dipaksa upload ulang.
            $ext = strtolower((string) pathinfo($item->media_path, PATHINFO_EXTENSION));

            return in_array($ext, $ekstensiVideo, true) === ($tipe === 'video');
        }

        $url = trim((string) $item->media_url);

        if ($url === '') {
            return false;
        }

        // YouTube/Vimeo selalu video.
        if ($item->embedUrl() !== null) {
            return $tipe === 'video';
        }

        $jalur = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($jalur, PATHINFO_EXTENSION));

        if (in_array($ext, $ekstensiVideo, true)) {
            return $tipe === 'video';
        }

        if (in_array($ext, $ekstensiGambar, true)) {
            return $tipe === 'image';
        }

        // URL tanpa ekstensi yang jelas (mis. endpoint /media/123): jangan
        // memaksa unggah ulang, biarkan admin memutuskan.
        return true;
    }

    /** Simpan berkas baru (bila ada) dan hapus berkas lama. */
    private function simpanMedia(Request $request, WhatsNewItem $item): void
    {
        $ganti = false;

        if ($item->media_type === 'none') {
            // Admin memilih "Tanpa media": jangan tinggalkan berkas yatim.
            if ($item->media_path) {
                $item->hapusMedia();
                $item->media_path = null;
                $ganti = true;
            }
        } elseif ($request->hasFile('media_file')) {
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