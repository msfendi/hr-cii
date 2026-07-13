<?php

namespace App\Http\Controllers;

use App\Models\WhatsappDevice;
use App\Models\HealthTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FonnteService;
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Str;

class RecruitmentController extends Controller
{
    protected $fonnteService;

    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
    }

    public function index(Request $request)
    {
        $status = $request->query('status');
        $tglPendaftaran = $request->query('tgl_pendaftaran');
        $query = DB::connection('cii')->table('PELAMAR')
            ->where('PELAMAR.IS_KONTRAK', 'FALSE')
            ->leftJoin('pelamar_details as pd', 'pd.id_pelamar', '=', 'PELAMAR.ID')
            ->select(
                'PELAMAR.*',
                'pd.id as detail_id',
                'pd.nomor_sim',
                'pd.warga_negara',
                'pd.ikut_kb',
                'pd.bakat_hobby',
                'pd.mode_transportasi',
                'pd.jabatan',
                'pd.department',
                'pd.bpjs_tk',
                'pd.bpjs_kes',
                'pd.alamat_skrg',
                'pd.kabupaten_kota_skrg',
                'pd.status_domisili',
                'pd.nama_ktk_darurat',
                'pd.hubungan',
                'pd.no_telp_darurat',
                'pd.pengalaman_kerja',
                'pd.data_ayah',
                'pd.data_ibu',
                'pd.saudara_kandung',
                'pd.data_anak',
                'pd.riwayat_pendidikan',
                'pd.motivasi',
                'pd.kegiatan_ekstra',
                'pd.file_surat_lamaran',
                'pd.file_cv',
                'pd.file_ktp',
                'pd.file_kk',
                'pd.file_ijasah',
                'pd.file_akta_kelahiran',
                'pd.file_skck',
                'pd.file_surat_sehat',
                'pd.file_pas_foto',
                'pd.tgl_test',
                'pd.tgl_interview',
                'pd.tgl_kesehatan',
                'pd.tgl_diterima',
                'pd.status_apply',
                'pd.is_test',
                'pd.is_interview',
                'pd.is_kesehatan',
                'pd.result_test',
                'pd.comment_test',
                'pd.result_kesehatan',
                'pd.comment_kesehatan',
                'pd.result_interview',
                'pd.comment_interview',
                'pd.result_user',
                'pd.comment_user',
                'pd.file_test',
                'pd.created_at'
            );

        if ($status === 'never_confirm') {
            $query->where('pd.status_apply', 'APPLIED');
        } elseif ($status === 'step_interview') {
            $query->where(function ($q) {
                $q->whereNull('pd.result_interview')->orWhereIn('pd.result_interview', ['', 'FALSE']);
            })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'step_kesehatan') {
            $query->where('pd.result_interview', 'TRUE')
                ->where(function ($q) {
                    $q->whereNull('pd.result_kesehatan')->orWhereIn('pd.result_kesehatan', ['', 'FALSE']);
                })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'step_teknis') {
            $query->where('pd.result_kesehatan', 'TRUE')
                ->where(function ($q) {
                    $q->whereNull('pd.result_test')->orWhereIn('pd.result_test', ['', 'FALSE']);
                })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'step_user') {
            $query->where('pd.result_test', 'TRUE')
                ->where(function ($q) {
                    $q->whereNull('pd.result_user')->orWhereIn('pd.result_user', ['', 'FALSE']);
                })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'ready_test') {
            $query->where('pd.status_apply', 'INVITATION TEST');
        } elseif ($status === 'ready_interview') {
            $query->where('pd.status_apply', 'CALLED TO INTERVIEW');
        } elseif ($status === 'decline') {
            $query->where('pd.status_apply', 'REJECTED');
        } elseif ($status === 'onboarding') {
            $query->where('pd.status_apply', 'ONBOARDING');
        }

        if ($tglPendaftaran) {
            $query->whereDate('pd.created_at', $tglPendaftaran);
        }

        $recruitments = $query->orderByDesc('PELAMAR.id')->get();

        // Map health test IDs by NIK for quick lookup in the blade
        $healthTestMap = HealthTest::select('id', 'nik')
            ->get()
            ->keyBy('nik')
            ->map(fn($h) => $h->id);

        return view('recruitment.index', compact('recruitments', 'status', 'healthTestMap'));

    }

    public function updatePenilaian(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);

        $updates = [
            'result_interview' => $request->result_interview,
            'comment_interview' => $request->comment_interview,
            'result_kesehatan' => $request->result_kesehatan,
            'comment_kesehatan' => $request->comment_kesehatan,
            'result_test' => $request->result_test,
            'comment_test' => $request->comment_test,
            'result_user' => $request->result_user,
            'comment_user' => $request->comment_user,
        ];

                
        $now = date('Y-m-d');

        if ($request->filled('result_interview')) {
            $updates['is_interview'] = 'TRUE';
            $updates['tgl_interview'] = DB::raw("COALESCE(tgl_interview, '$now')");
        }
        if ($request->filled('result_kesehatan')) {
            $updates['is_kesehatan'] = 'TRUE';
            $updates['tgl_kesehatan'] = DB::raw("COALESCE(tgl_kesehatan, '$now')");
        }
        if ($request->filled('result_test')) {
            $updates['is_test'] = 'TRUE';
            $updates['tgl_test'] = DB::raw("COALESCE(tgl_test, '$now')");
        }

        if ($request->hasFile('file_test')) {
            $file = $request->file('file_test');
            $filename = time() . '_teknis_' . $file->getClientOriginalName();
            $path = $file->storeAs('recruitment/teknis', $filename, 'public');
            $updates['file_test'] = $path;
        }

        // Auto status_apply: FALSE di mana saja -> REJECTED, semua TRUE -> ONBOARDING
        $results = array_filter($updates, fn($v, $k) => str_starts_with($k, 'result_'), ARRAY_FILTER_USE_BOTH);
        if (in_array('FALSE', $results)) {
            $updates['status_apply'] = 'REJECTED';
        } elseif (count($results) === 4 && !in_array(null, $results) && !in_array('', $results) && count(array_unique($results)) === 1 && reset($results) === 'TRUE') {
            $updates['status_apply'] = 'ONBOARDING';
        }

        DB::connection('cii')->table('pelamar_details')
            ->where('id_pelamar', $request->id)
            ->update($updates);

        Alert::success('Berhasil', 'Penilaian pelamar berhasil diperbarui!');
        return back()->with('success', 'Penilaian pelamar berhasil diperbarui!');
    }

    public function sendWhatsApp(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'type' => 'required',
            'nama' => 'required',
            'nomor_hp' => 'required',
            'message' => 'required',
        ]);

        $sendWa = $request->has('send_wa');
        $waSuccess = true;
        $response = [];

        if ($sendWa) {
            $devices = WhatsappDevice::where('is_active', true)->get();

            if (count($devices) == 0) {
                Alert::warning('Whatsapp Failed!', 'Device Not Linked!');
                return back()->with('error', 'Failed to send WhatsApp message: Device not linked');
            }

            $response = $this->fonnteService->sendMessage($devices[0]->id, $request->nomor_hp, $request->message);
            $waSuccess = $response['status'] ?? false;
        }

        if ($waSuccess) {
            $updates = [
                'status_apply' => strtoupper(str_replace('_', ' ', $request->type)),
            ];

            if ($request->type === 'invitation') {
                $updates['is_test'] = 'TRUE';
                $updates['status_apply'] = 'INVITATION TEST';
                if ($request->filled('tgl_schedule')) {
                    $updates['tgl_test'] = $request->tgl_schedule;
                }
            } elseif ($request->type === 'interview') {
                $updates['is_interview'] = 'TRUE';
                $updates['status_apply'] = 'CALLED TO INTERVIEW';
                if ($request->filled('tgl_schedule')) {
                    $updates['tgl_interview'] = $request->tgl_schedule;
                }
            } elseif ($request->type === 'final') {
                $updates['status_apply'] = 'ONBOARDING';
                if ($request->filled('tgl_schedule')) {
                    $updates['tgl_diterima'] = $request->tgl_schedule;
                }
            } elseif ($request->type === 'rejection') {
                $updates['status_apply'] = 'REJECTED';
            }

            DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $request->id)
                ->update($updates);

            if ($sendWa) {
                Alert::success('Berhasil', 'Status diperbarui dan pesan WhatsApp berhasil dikirim!');
                return back()->with('success', 'Status diperbarui dan pesan WhatsApp berhasil dikirim untuk ' . $request->nama);
            } else {
                Alert::success('Berhasil', 'Status berhasil diperbarui (tanpa mengirim WhatsApp)');
                return back()->with('success', 'Status berhasil diperbarui untuk ' . $request->nama);
            }
        }

        Alert::error('Whatsapp Failed!', 'Gagal mengirim pesan WhatsApp.');
        return back()->with('error', 'Failed to send WhatsApp message: ' . ($response['reason'] ?? 'Unknown error'));
    }

    
    public function edit($id)
    {
        $pelamar = DB::connection('cii')->table('PELAMAR')
            ->leftJoin('pelamar_details as pd', 'pd.id_pelamar', '=', 'PELAMAR.ID')
            ->where('PELAMAR.ID', $id)
            ->select('PELAMAR.*', 'pd.*', 'PELAMAR.ID as id', 'pd.id as detail_id')
            ->first();

        if (!$pelamar) {
            Alert::error('Error', 'Data pelamar tidak ditemukan');
            return redirect()->route('recruitment.index');
        }

        // Decode JSON fields if needed for view
        $pelamar->pengalaman_kerja = $pelamar->pengalaman_kerja ? json_decode($pelamar->pengalaman_kerja, true) : null;
        $pelamar->data_ayah = $pelamar->data_ayah ? json_decode($pelamar->data_ayah, true) : null;
        $pelamar->data_ibu = $pelamar->data_ibu ? json_decode($pelamar->data_ibu, true) : null;
        $pelamar->saudara_kandung = $pelamar->saudara_kandung ? json_decode($pelamar->saudara_kandung, true) : null;
        $pelamar->data_anak = $pelamar->data_anak ? json_decode($pelamar->data_anak, true) : null;
        $pelamar->riwayat_pendidikan = $pelamar->riwayat_pendidikan ? json_decode($pelamar->riwayat_pendidikan, true) : null;

        return view('recruitment.edit', compact('pelamar'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|max:16',
            'no_kk' => 'required|string|max:16',
        ]);

        try {
            DB::connection('cii')->beginTransaction();

            $umur = null;
            if ($request->filled('tanggal_lahir')) {
                $diff = Carbon::parse($request->tanggal_lahir)->diff(Carbon::now());
                $umur = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
            }

            // Update PELAMAR
            DB::connection('cii')->table('PELAMAR')
                ->where('ID', $id)
                ->update([
                    'NAMA' => strtoupper($request->nama_lengkap ?? '-'),
                    'NIK' => $request->nik,
                    'NO_KK' => $request->no_kk,
                    'JENIS_KELAMIN' => strtoupper($request->jenis_kelamin ?? '-'),
                    'TMPT_LAHIR' => strtoupper($request->tempat_lahir ?? '-'),
                    'TGL_LAHIR' => $request->tanggal_lahir,
                    'UMUR' => $umur ?? '-',
                    'STATUS' => $request->status_pernikahan ?? '-',
                    'TANGGUNGAN' => $request->tanggungan ?? 0,
                    'AGAMA' => strtoupper($request->agama ?? '-'),
                    'HP' => $request->nomor_hp ?? '-',
                    'ALAMAT_LENGKAP' => strtoupper($request->alamat_asal ?? '-'),
                    'KABUPATEN' => strtoupper($request->kab_kota_asal ?? '-'),
                    'ALAMAT_DOMISILI' => strtoupper($request->status_domisili_asal ?? '-'),
                    'PENDIDIKAN' => strtoupper($request->pendidikan ?? '-'),
                    'JURUSAN' => strtoupper($request->jurusan ?? '-'),
                    'NAMA_SEKOLAH' => strtoupper($request->nama_sekolah ?? '-'),
                    'TINGGI_BADAN' => $request->tinggi_badan ?? 0,
                    'BERAT_BADAN' => $request->berat_badan ?? 0,
                ]);

            // Handle Files
            $fileFields = [
                'surat_lamaran' => 'file_surat_lamaran',
                'cv' => 'file_cv',
                'scan_ktp' => 'file_ktp',
                'scan_kk' => 'file_kk',
                'pas_foto' => 'file_pas_foto',
                'ijazah' => 'file_ijasah',
                'scan_akta_kelahiran' => 'file_akta_kelahiran',
                'scan_skck' => 'file_skck',
                'scan_blanko_kesehatan' => 'file_surat_sehat'
            ];

            $namaPelamar = Str::slug($request->nama_lengkap ?? 'pelamar', '_');
            $timestamp = Carbon::now()->format('Ymd-His');
            $detailUpdates = [
                'nomor_sim' => $request->sim,
                'warga_negara' => $request->warga_negara,
                'ikut_kb' => ($request->kb ?? 'Tidak') === 'Ya' ? 1 : 0,
                'bakat_hobby' => $request->hobby,
                'mode_transportasi' => $request->transportasi,
                'jabatan' => $request->jabatan,
                'department' => $request->department,
                'bpjs_tk' => $request->bpjs_tk,
                'bpjs_kes' => $request->bpjs_kes,
                'alamat_skrg' => $request->alamat_sekarang ?? $request->alamat_asal,
                'kabupaten_kota_skrg' => $request->kab_kota_sekarang ?? $request->kab_kota_asal,
                'status_domisili' => $request->status_domisili_sekarang ?? $request->status_domisili_asal,
                'nama_ktk_darurat' => $request->nama_darurat,
                'hubungan' => $request->hubungan_darurat,
                'no_telp_darurat' => $request->no_telepon_darurat,
                'motivasi' => $request->motivasi,
                'kegiatan_ekstra' => $request->kegiatan_ekstra,
                'data_ayah' => $request->filled('data_ayah') ? json_encode($request->data_ayah) : null,
                'data_ibu' => $request->filled('data_ibu') ? json_encode($request->data_ibu) : null,
                'saudara_kandung' => $request->filled('saudara_kandung') ? json_encode(array_values(array_filter($request->saudara_kandung, fn($v) => !empty(array_filter($v))))) : null,
                'data_anak' => $request->filled('data_anak') ? json_encode(array_values(array_filter($request->data_anak, fn($v) => !empty(array_filter($v))))) : null,
                'riwayat_pendidikan' => $request->filled('riwayat_pendidikan') ? json_encode(array_values(array_filter($request->riwayat_pendidikan, fn($v) => !empty(array_filter($v))))) : null,
                'pengalaman_kerja' => $request->filled('pengalaman_kerja') ? json_encode(array_values(array_filter($request->pengalaman_kerja, fn($v) => !empty(array_filter($v))))) : null,
            ];

            foreach ($fileFields as $inputName => $dbField) {
                if ($request->hasFile($inputName)) {
                    $file = $request->file($inputName);
                    $ext = $file->extension();
                    $filename = "{$inputName}_{$namaPelamar}_{$timestamp}.{$ext}";
                    $folder = "pelamar/{$inputName}";
                    $detailUpdates[$dbField] = $file->storeAs($folder, $filename, 'public');
                }
            }

            DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $id)
                ->update($detailUpdates);

            DB::connection('cii')->commit();

            Alert::success('Berhasil', 'Data pelamar berhasil diperbarui!');
            return redirect()->route('recruitment.index');

        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            Alert::error('Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
            return back()->withInput();
        }
    }
}