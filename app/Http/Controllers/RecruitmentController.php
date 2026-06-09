<?php

namespace App\Http\Controllers;

use App\Models\WhatsappDevice;
use App\Models\HealthTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FonnteService;
use RealRashid\SweetAlert\Facades\Alert;

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
                'pd.file_test'
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
        $devices = WhatsappDevice::where('is_active', true)->get();

        // dd(count($devices));
        if (count($devices) == 0) {
            Alert::warning('Whatsapp Failed!', 'Device Not Linked!');
            return back()->with('error', 'Failed to send WhatsApp message: ' . ($response['reason'] ?? 'Unknown error'));
        }

        $request->validate([
            'id' => 'required',
            'type' => 'required',
            'nama' => 'required',
            'nomor_hp' => 'required',
            'message' => 'required',
        ]);

        $response = $this->fonnteService->sendMessage($devices[0]->id, $request->nomor_hp, $request->message);

        if ($response['status'] ?? false) {
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

            Alert::success('Whatsapp Send!', 'Whatsapp message send succesfully!');
            return back()->with('success', 'WhatsApp message sent and status updated for ' . $request->nama);
        }

        return back()->with('error', 'Failed to send WhatsApp message: ' . ($response['reason'] ?? 'Unknown error'));
    }
}
