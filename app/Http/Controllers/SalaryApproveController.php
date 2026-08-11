<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSalaryApprovalSteps;
use App\Models\SalaryApprove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class SalaryApproveController extends Controller
{
    use HandlesSalaryApprovalSteps;

    /**
     * Submit dari modal "Pengajuan Gaji" di recruitment/index.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_pelamar'        => 'required',
            'expected_salary'   => 'required|numeric|min:0',
            'management_npk'    => 'required|array|min:1',
            'management_npk.*'  => 'required|string',
            'gm_npk'            => 'required|array|min:1',
            'gm_npk.*'          => 'required|string',
        ]);

        $pelamar = DB::connection('cii')->table('PELAMAR')->where('ID', $request->id_pelamar)->first();

        if (!$pelamar) {
            Alert::error('Error', 'Data pelamar tidak ditemukan');
            return back();
        }

        $sedangProses = SalaryApprove::where('id_pelamar', $request->id_pelamar)
            ->where('status', 'pending')
            ->exists();

        if ($sedangProses) {
            Alert::warning('Gagal', 'Pengajuan gaji pelamar ini masih dalam proses approval');
            return back();
        }

        // Urutan tetap: [0] management_dept, [1] general_manager -> "bertingkat"
        $progress = [
            [
                'npk'    => json_encode(array_values($request->management_npk)),
                'status' => 'pending',
            ],
            [
                'npk'    => json_encode(array_values($request->gm_npk)),
                'status' => 'pending',
            ],
        ];

        $salary = SalaryApprove::create([
            'id_pelamar'      => $request->id_pelamar,
            'expected_salary' => $request->expected_salary,
            'progress'        => $progress,
            'status'          => 'pending',
            'requested_by'    => Auth::user()->npk ?? Auth::user()->name,
        ]);

        // Kirim email notifikasi ke approver tahap 1 (Management Dept)
        $this->notifyApproversByNpk($request->management_npk, $salary, 'Management Dept');

        Alert::success('Berhasil', 'Pengajuan gaji berhasil dikirim untuk approval');
        return back()->with('success', 'Pengajuan gaji berhasil dikirim');
    }

    /**
     * Update pengajuan (expected_salary + approver terpilih) — HANYA boleh
     * selama belum ada satupun approver yang memproses (progress step 0 masih
     * persis 'pending'). Begitu Management Dept sudah mulai approve, progress
     * berubah dari 'pending' jadi array status per-npk sehingga isEditable
     * otomatis false dan endpoint ini menolak perubahan.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'expected_salary'   => 'required|numeric|min:0',
            'management_npk'    => 'required|array|min:1',
            'management_npk.*'  => 'required|string',
            'gm_npk'             => 'required|array|min:1',
            'gm_npk.*'           => 'required|string',
        ]);

        $data = SalaryApprove::findOrFail($id);

        $isEditable = $data->status === 'pending'
            && (($data->progress[0]['status'] ?? null) === 'pending');

        if (!$isEditable) {
            Alert::error('Gagal', 'Pengajuan ini sudah mulai diproses approval, tidak bisa diubah lagi.');
            return back();
        }

        $progress = [
            [
                'npk'    => json_encode(array_values($request->management_npk)),
                'status' => 'pending',
            ],
            [
                'npk'    => json_encode(array_values($request->gm_npk)),
                'status' => 'pending',
            ],
        ];

        $data->update([
            'expected_salary' => $request->expected_salary,
            'progress'        => $progress,
        ]);

        // Kirim email notifikasi ulang ke approver tahap 1 (Management Dept)
        $this->notifyApproversByNpk($request->management_npk, $data, 'Management Dept');

        Alert::success('Berhasil', 'Pengajuan gaji berhasil diperbarui.');
        return back()->with('success', 'Pengajuan gaji berhasil diperbarui.');
    }

    /**
     * Halaman terpisah: daftar pengajuan + status giliran approve user login.
     *
     * salary_approve ada di koneksi default, sedangkan PELAMAR & pelamar_details
     * ada di koneksi 'cii' -> gak bisa di-join lewat query builder biasa, jadi
     * di-merge manual di PHP.
     *
     * Visibilitas dibatasi: baris hanya ditampilkan ke user yang namanya
     * (npk-nya) ada di salah satu step approval — baik yang sudah dia approve
     * maupun yang masih dia tunggu — persis seperti yang di-assign HR saat
     * pertama mengajukan. User lain (termasuk HR yang mengajukan, kalau bukan
     * approver) tidak melihat baris ini sama sekali di halaman ini.
     */
    public function index(Request $request)
    {
        $npkLogin = Auth::user()->npk;

        $salaries = SalaryApprove::orderByDesc('id')->get();

        $pelamarIds = $salaries->pluck('id_pelamar')->filter()->unique()->values()->toArray();

        // Nama pelamar dari tabel PELAMAR (koneksi cii)
        $pelamarMap = DB::connection('cii')->table('PELAMAR')
            ->whereIn('ID', $pelamarIds)
            ->select('ID', 'NPK', 'NAMA', 'NIK')
            ->get()
            ->keyBy('ID');

        // Jabatan/department dari pelamar_details (koneksi cii)
        $detailMap = DB::connection('cii')->table('pelamar_details')
            ->whereIn('id_pelamar', $pelamarIds)
            ->select('id_pelamar', 'jabatan', 'department')
            ->get()
            ->keyBy('id_pelamar');

        // Resolve nama approver (npk -> name) sekali query buat semua baris
        $userNameMap = $this->resolveApproverNames($salaries);

        $data = $salaries->map(function ($item) use ($npkLogin, $pelamarMap, $detailMap, $userNameMap) {
            $pelamar = $pelamarMap->get($item->id_pelamar);
            $detail  = $detailMap->get($item->id_pelamar);

            $item->nama_pelamar = $pelamar->NAMA ?? '-';
            $item->nik_pelamar  = $pelamar->NIK ?? '-';
            $item->jabatan      = $detail->jabatan ?? '-';
            $item->department   = $detail->department ?? '-';

            $progressRaw  = $item->progress ?? [];
            $currentIndex = $this->currentStepIndex($progressRaw);

            $item->current_step = $currentIndex;
            $item->steps         = $this->buildStepsDisplay($progressRaw, $userNameMap);
            $item->can_approve   = false;

            if ($currentIndex !== false && $item->status === 'pending') {
                $npkList = $this->decodeNpkList($progressRaw[$currentIndex]['npk'] ?? null);
                $item->can_approve = $npkLogin && in_array($npkLogin, $npkList);
            }

            return $item;
        })->filter(function ($item) use ($npkLogin) {
            return $this->isAssignedApprover($item, $npkLogin);
        })->values();

        return view('salary_approve.index', compact('data'));
    }

    /**
     * Approve tahap yang lagi berjalan.
     * - "hanya oleh ybs"  -> npk yang dipakai HARUS npk user yang sedang login
     *                        (bukan input dari request), dan harus ada di daftar
     *                        approver tahap yang lagi aktif.
     * - "bertingkat"      -> tahap berikutnya baru aktif setelah tahap
     *                        sebelumnya approve 100%. currentStepIndex() selalu
     *                        mengembalikan tahap paling awal yang belum selesai,
     *                        jadi approver tahap 2 (GM) gak akan pernah bisa
     *                        approve duluan sebelum tahap 1 lengkap.
     */
    public function approve(Request $request, $id)
    {
        $user     = Auth::user();
        $npkLogin = $user->npk ?? null;

        if (!$npkLogin) {
            Alert::error('Gagal', 'Akun Anda tidak memiliki NPK terdaftar, tidak bisa melakukan approval.');
            return back();
        }

        $data = SalaryApprove::findOrFail($id);

        if ($data->status !== 'pending') {
            Alert::warning('Gagal', 'Pengajuan ini sudah tidak berjalan (status: ' . $data->status . ').');
            return back();
        }

        $progress   = collect($data->progress);
        $approvedAt = collect($data->approved_at ?? []);

        $currentIndex = $this->currentStepIndex($progress);

        if ($currentIndex === false) {
            Alert::info('Info', 'Semua tahap sudah approve.');
            return back();
        }

        $row     = $progress[$currentIndex];
        $npkList = $this->decodeNpkList($row['npk'] ?? null);

        if (empty($npkList)) {
            Alert::error('Gagal', 'Format NPK approval tidak valid.');
            return back();
        }

        $userIndex = array_search($npkLogin, $npkList);

        if ($userIndex === false) {
            Alert::error('Gagal', 'Anda bukan approver pada tahap ini.');
            return back();
        }

        $statusList = $row['status'] === 'pending'
            ? array_fill(0, count($npkList), 'waiting')
            : json_decode($row['status'], true);

        if (($statusList[$userIndex] ?? null) === 'approve') {
            Alert::info('Info', 'Anda sudah approve tahap ini sebelumnya.');
            return back();
        }

        // Step terakhir = General Manager -> wajib isi approved_salary saat approve
        $isLastStep = $currentIndex === $progress->count() - 1;
        if ($isLastStep) {
            $request->validate(['approved_salary' => 'required|numeric|min:0']);
        }

        $statusList[$userIndex] = 'approve';

        $approvedAtArr = $approvedAt->toArray();
        $approvedAtArr[$currentIndex][$userIndex] = now()->toDateTimeString();

        $allApproved = collect($statusList)->every(fn ($s) => $s === 'approve');

        $progressArr = $progress->toArray();
        $progressArr[$currentIndex]['status'] = $allApproved ? 'approve' : json_encode($statusList);

        $progress   = collect($progressArr);
        $approvedAt = collect($approvedAtArr);

        $finalApprove = $progress->every(fn ($item) => $item['status'] === 'approve');

        $updates = [
            'progress'    => $progress->values(),
            'approved_at' => $approvedAt->values(),
            'status'      => $finalApprove ? 'finish' : 'pending',
        ];

        if ($isLastStep && $allApproved && $request->filled('approved_salary')) {
            $updates['approved_salary'] = $request->approved_salary;
        }

        $data->update($updates);

        if ($finalApprove) {
            // Kalau penilaian pelamar ini juga sudah lolos semua, ini yang
            // akan memindahkan status_apply-nya ke ONBOARDING.
            $this->maybeFinalizeStaffOnboarding($data->id_pelamar);
        } elseif ($allApproved && isset($progressArr[$currentIndex + 1])) {
            // Step saat ini baru saja 100% selesai -> kirim email notifikasi ke approver tahap BERIKUTNYA (misal: General Manager)
            $nextStep = $progressArr[$currentIndex + 1];
            $nextNpkList = $this->decodeNpkList($nextStep['npk'] ?? null);
            $stepLabels = ['Management Dept', 'General Manager'];
            $nextStepName = $stepLabels[$currentIndex + 1] ?? ('Step ' . ($currentIndex + 2));

            $this->notifyApproversByNpk($nextNpkList, $data, $nextStepName);
        }

        Alert::success('Berhasil', 'Pengajuan gaji berhasil diapprove');
        return back()->with('success', 'Berhasil approve');
    }

    /**
     * Tolak pengajuan. Sama seperti approve(): hanya approver yang sedang
     * kebagian giliran (tahap yang lagi aktif) yang boleh menolak.
     */
    public function reject(Request $request, $id)
    {
        $user     = Auth::user();
        $npkLogin = $user->npk ?? null;

        $data = SalaryApprove::findOrFail($id);

        if ($data->status !== 'pending') {
            Alert::warning('Gagal', 'Pengajuan ini sudah tidak berjalan.');
            return back();
        }

        $currentIndex = $this->currentStepIndex($data->progress ?? []);

        if ($currentIndex === false) {
            Alert::info('Info', 'Tidak ada tahap approval yang aktif.');
            return back();
        }

        $npkList = $this->decodeNpkList($data->progress[$currentIndex]['npk'] ?? null);

        if (!$npkLogin || !in_array($npkLogin, $npkList)) {
            Alert::error('Gagal', 'Anda bukan approver pada tahap ini, tidak bisa menolak pengajuan.');
            return back();
        }

        $data->update(['status' => 'rejected']);

        Alert::success('Berhasil', 'Pengajuan gaji ditolak.');
        return back()->with('success', 'Pengajuan gaji ditolak.');
    }
}