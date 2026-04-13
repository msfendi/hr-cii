<?php

namespace App\Imports;

use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class OvertimeImport implements ToCollection
{
    protected $deptGroup;
    protected $month;

    public function __construct($deptGroup, $month = null)
    {
        $this->deptGroup = $deptGroup;
        $this->month = $month ?: date('Y-m');
    }

    /**
     * @return int
     */

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */

    public function collection(Collection $rows)
    {
        try {
            $header = $rows->first();
            $rows->shift(); // hapus header

            $year = explode('-', $this->month)[0];
            $month = explode('-', $this->month)[1];

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $npk = $row[0];
                    $nama = $row[1];
                    $bagian = $row[2];
                    
                    if (empty($npk)) {
                        continue;
                    }

                    foreach ($header as $index => $tanggal) {
                        if ($index < 3 || empty($tanggal)) {
                            continue;
                        }
                        $jamLembur = $row[$index] ?? null;
                        $date = Carbon::createFromDate(
                            $year,
                            $month,
                            $tanggal
                        )->format('Y-m-d');

                        $existingFromLeave = Overtime::where('NPK', $npk)->where('OVERTIME_DATE', $date)->first();
                        if ($existingFromLeave && !empty($existingFromLeave->JUMLAH_JAM_LEMBUR) && !is_numeric(trim($existingFromLeave->JUMLAH_JAM_LEMBUR))) {
                            $existingFromLeave->update([
                                'DEPT_GROUP' => $this->deptGroup,
                            ]);
                            continue;
                        }

                        Overtime::updateOrCreate(
                            [
                                'NPK' => $npk,
                                'OVERTIME_DATE' => $date,
                            ],
                            [
                                'NAMA_KARYAWAN' => $nama,
                                'BAGIAN' => $bagian,
                                'DAY' => Carbon::parse($date)->translatedFormat('l'),
                                'JUMLAH_JAM_LEMBUR' => $jamLembur !== '' ? $jamLembur : null,
                                'DEPT_GROUP' => $this->deptGroup,
                            ]
                        );
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // Rethrow so it can be caught by the controller and shown to user
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
