<?php

namespace App\Http\Controllers;

use App\Models\WhatsappDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FonnteService;

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
        $query = DB::connection('cii')->table('PELAMAR')->where('IS_KONTRAK', 'FALSE');

        if ($status === 'never_confirm') {
            $query->whereNull('STATUS_APPLY');
        } elseif ($status === 'ready_test') {
            $query->where('STATUS_APPLY', 'INVITATION TEST');
        } elseif ($status === 'ready_interview') {
            $query->where('STATUS_APPLY', 'CALLED TO INTERVIEW');
        } elseif ($status === 'decline') {
            $query->where('STATUS_APPLY', 'REJECTED');
        } elseif ($status === 'joining') {
            $query->where('STATUS_APPLY', 'FINAL RESULT');
        }

        $recruitments = $query->get();
        return view('recruitment.index', compact('recruitments', 'status'));
    }

    public function sendWhatsApp(Request $request)
    {
        $devices = WhatsappDevice::where('is_active', true)->get();

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
            } elseif ($request->type === 'interview') {
                $updates['is_interview'] = 'TRUE';
                $updates['status_apply'] = 'CALLED TO INTERVIEW';
            } elseif ($request->type === 'final') {
                $updates['status_apply'] = 'FINAL RESULT';
            } elseif ($request->type === 'rejection') {
                $updates['status_apply'] = 'REJECTED';
            }

            DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $request->id)
                ->update($updates);

            return back()->with('success', 'WhatsApp message sent and status updated for ' . $request->nama);
        }

        return back()->with('error', 'Failed to send WhatsApp message: ' . ($response['reason'] ?? 'Unknown error'));
    }
}
