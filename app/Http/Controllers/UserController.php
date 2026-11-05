<?php

namespace App\Http\Controllers;

use App\Models\ModelHasRoles;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->leftJoin('model_has_roles as mhr', function ($join) {
                $join->on('users.id', '=', 'mhr.model_id')
                    ->where('mhr.model_type', User::class);
            })
            ->leftJoin('roles as r', 'mhr.role_id', '=', 'r.id')
            ->select(
                'users.*',
                'r.name as role'
            )
            ->orderBy('users.name')
            ->get();

        return view('user.index', compact('users'));
    }

    public function detail($id)
    {
        $users = User::find($id);
        return view('auth.detail', ['users' => $users]);
    }

    public function delete($id)
    {
        $users = User::find($id);
        $users->delete();
        Alert::success('Delete Successfully!', 'User ' . $users->name . ' successfully deleted!');
        return redirect()->intended('user/index');
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        // jangan sampai admin menonaktifkan akunnya sendiri
        if (Auth::id() === $user->id) {
            Alert::error('Gagal!', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            return redirect('user/index');
        }

        $user->disabled = $user->disabled ? 0 : 1;
        $user->save();

        if ($user->disabled) {
            Alert::success('Disabled Successfully!', 'User ' . $user->name . ' berhasil dinonaktifkan!');
        } else {
            Alert::success('Enabled Successfully!', 'User ' . $user->name . ' berhasil diaktifkan!');
        }

        return redirect('user/index');
    }

    public function assign($id)
    {
        $modelhasroles = User::select('users.name', 'users.email', 'users.id', 'model_has_roles.*')
            ->leftJoin('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.id', '=', $id)
            ->get();
        $roles = Role::all();
        return view('auth.assign', compact('modelhasroles', 'roles'));
    }

    public function assignrole(Request $request)
    {
        $modelhasroles = ModelHasRoles::where('model_id', '=', $request->id)
            ->where('model_type', '=', 'App\Models\User')
            ->delete();
        ModelHasRoles::updateOrCreate(
            [
                'role_id' => $request->role_id,
                'model_type' => 'App\Models\User',
                'model_id' => $request->id
            ]
        );

        Alert::success('Assign Successfully!', 'User successfully assigned!');
        return redirect()->intended('user/index');
    }

    public function update(Request $request)
    {
        $user = User::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            // 'npk' => 'required|max:255',
            'email' => 'required|email|max:255|',
            'password' => 'nullable|min:8',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user->fill([
            'name' => $request->name,
            'npk' => $request->npk,
            'email' => $request->email,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        // dd($user, $request->password);

        $user->save();

        Alert::success('Update Successfully!', 'User ' . $request->name . ' successfully updated!');
        return redirect()->intended('user/index');
    }

    /**
     * Ambil data signature user (JSON untuk modal).
     */
    public function signatureShow($id)
    {
        // PERBAIKAN V4 (2026-11-05): error DB tidak boleh mentah-mentah
        // ditampilkan ke user ("Database connection [sqlsrv] not configured").
        // Signature bersifat non-kritis: kalau tabel/koneksi bermasalah,
        // halaman tetap jalan dengan signature kosong.
        try {
            $sig = UserSignature::where('user_id', $id)->latest()->first();
            $url = $sig ? Storage::disk('public')->url('signature/' . basename($sig->signature_img)) : null;
        } catch (\Throwable $e) {
            \Log::error('Gagal membaca signature user ' . $id . ': ' . $e->getMessage());
            $sig = null;
            $url = null;
        }

        return response()->json([
            'signature' => $sig,
            'url'       => $url,
        ]);
    }

    /**
     * Simpan signature: upload file ATAU data:image base64 dari canvas draw.
     */
    public function signatureStore(Request $request, $id)
    {
        $request->validate([
            'mode' => 'required|in:upload,draw,select',
        ]);

        $filename = null;
        $disk     = Storage::disk('public');
        $dir      = 'signature';

        if ($request->mode === 'upload') {
            $request->validate(['file' => 'required|file|mimes:png,jpg,jpeg|max:5120']);
            $file     = $request->file('file');
            $filename = 'sig_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $disk->putFileAs($dir, $file, $filename);

        } elseif ($request->mode === 'draw') {
            $request->validate(['image_data' => 'required|string']);
            $data = $request->input('image_data');
            // Ambil bagian base64 setelah koma
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $data);
            $binary = base64_decode($base64);
            $filename = 'sig_' . $id . '_' . time() . '.png';
            $disk->put($dir . '/' . $filename, $binary);

        } elseif ($request->mode === 'select') {
            $request->validate(['selected_file' => 'required|string']);
            $sel = basename($request->input('selected_file'));
            // Pastikan file ada di folder signature
            if (!$disk->exists($dir . '/' . $sel)) {
                return response()->json(['error' => 'File tidak ditemukan.'], 422);
            }
            $filename = $sel;
        }

        // Upsert
        try {
            UserSignature::updateOrCreate(
                ['user_id' => (string) $id],
                ['signature_img' => 'signature/' . $filename]
            );
        } catch (\Throwable $e) {
            \Log::error('Gagal menyimpan signature user ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'File signature tersimpan, tetapi data database gagal ditulis. Silakan cek koneksi database (tabel signatures) lalu coba lagi.',
                'detail'  => $e->getMessage(),
            ], 500);
        }

        return response()->json(['success' => true, 'filename' => $filename]);
    }

    /**
     * Daftar file PNG di storage/app/public/signature untuk tab Select.
     */
    public function signatureFiles()
    {
        $disk  = Storage::disk('public');
        $files = $disk->files('signature');
        $list  = [];
        foreach ($files as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg'])) continue;
            $list[] = [
                'filename' => basename($f),
                'url'      => $disk->url($f),
            ];
        }
        return response()->json($list);
    }
}

