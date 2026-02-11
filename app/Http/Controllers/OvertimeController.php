<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Exports\OvertimeTemplateExport;
use App\Models\Overtime;
use App\Imports\OvertimeImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class OvertimeController extends Controller
{
    public function index()
    {
        $departments = Overtime::select('BAGIAN')->distinct()->get();

        return view('overtime.index', compact('departments'));
    }

    public function calendarOvertime()
    {
        $departments = Overtime::select('BAGIAN')->distinct()->get();
        
        return view('overtime.calendar', compact('departments'));
    }

    public function downloadTemplateOvertime(Request $request)
    {
        $date = $request->input('date');

        if (!$date) {
            return redirect()->back()->with('error', 'Silahkan pilih tanggal.');
        }

        return Excel::download(new OvertimeTemplateExport($date), 'template_overtime_' . $date . '.xlsx');
    }

    public function importOvertime(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls',
            ]);

            $file = $request->file('file');

            Excel::import(new OvertimeImport, $file);

            return redirect()->back()->with('success', 'Data overtime berhasil diimpor.');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', 'Gagal mengimpor data overtime: ' . $th->getMessage());
        }
    }

    public function getData(Request $request)
    {
        $overtimes = Overtime::all();
        return response()->json(['data' => $overtimes]);
    }

    public function calendarDisplay(Request $request)
    {
        $month = $request->input('month', date('Y-m'));
        $dept = $request->input('department');
        $startDate = \Carbon\Carbon::parse($month)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($month)->endOfMonth();

        $query = Overtime::whereBetween('OVERTIME_DATE', [$startDate, $endDate]);

        if ($dept) {
            $query->where('JUMLAH_JAM_LEMBUR', 1);
        }

        $overtimes = $query->orderBy('OVERTIME_DATE')->get();

        // Fetch holidays
        $holidays = Cache::remember('holidays_calendar', 86400, function () {
            try {
                $response = Http::get('https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json');
                return $response->json();
            } catch (\Exception $e) {
                return [];
            }
        });

        $pivoted = $overtimes->groupBy('NPK')->map(function ($group) use ($holidays) {
            $first = $group->first();
            $row = [
                'NPK' => $first->NPK,
                'NAMA_KARYAWAN' => $first->NAMA_KARYAWAN,
                'BAGIAN' => $first->BAGIAN,
            ];

            // Calculate custom fields
            $lemburResmi = $group->filter(function ($record) use ($holidays) {
                $date = \Carbon\Carbon::parse($record->OVERTIME_DATE);
                $isWeekday = !$date->isWeekend();
                $dateString = $date->format('Y-m-d');
                $isHoliday = isset($holidays[$dateString]) && $holidays[$dateString]['holiday'] === true;

                return $isWeekday &&
                    !$isHoliday &&
                    is_numeric($record->JUMLAH_JAM_LEMBUR) &&
                    $record->JUMLAH_JAM_LEMBUR >= 1 &&
                    $record->JUMLAH_JAM_LEMBUR <= 8;
            });

            // Field '1': Count of valid overtime days
            $field1 = $lemburResmi->count();

            // Field '2': Sum of hours > 1 minus count of days > 1
            $overOne = $lemburResmi->filter(function ($record) {
                return $record->JUMLAH_JAM_LEMBUR > 1;
            });
            $sumOverOne = $overOne->sum('JUMLAH_JAM_LEMBUR');
            $countOverOne = $overOne->count();
            $field2 = $sumOverOne - $countOverOne;

            // Total Kehadiran: Count days that are Numeric or Null (exclude text codes like CT, MA) AND Weekdays
            $totalKehadiran = $group->filter(function ($record) use ($holidays) {
                $isWeekday = !\Carbon\Carbon::parse($record->OVERTIME_DATE)->isWeekend();
                $val = $record->JUMLAH_JAM_LEMBUR;
                $dateString = \Carbon\Carbon::parse($record->OVERTIME_DATE)->format('Y-m-d');
                $isHoliday = isset($holidays[$dateString]) && $holidays[$dateString]['holiday'] === true;
                return $isWeekday && (is_numeric($val) || is_null($val) || $val === '') && !$isHoliday;
            })->count();

            // Lembur Khusus: Sum hours > 4 on Weekends OR Holidays
            $lemburKhusus = $group->filter(function ($record) use ($holidays) {
                $val = $record->JUMLAH_JAM_LEMBUR;
                if (!is_numeric($val) || $val <= 4) {
                    return false;
                }

                $date = \Carbon\Carbon::parse($record->OVERTIME_DATE);
                $isWeekend = $date->isWeekend();
                $dateString = $date->format('Y-m-d');
                $isHoliday = isset($holidays[$dateString]) && $holidays[$dateString]['holiday'] === true;

                return $isWeekend || $isHoliday;
            })->sum('JUMLAH_JAM_LEMBUR');

            // Count dan kelmpokan untuk yang value nya character
            $lemburCharacter = $group->filter(function ($record) {
                return !is_numeric($record->JUMLAH_JAM_LEMBUR);
            });
            $countLemburCharacter = $lemburCharacter->count();
            $lemburCharacterGroup = $lemburCharacter->groupBy('JUMLAH_JAM_LEMBUR');


            foreach ($group as $record) {
                $date = \Carbon\Carbon::parse($record->OVERTIME_DATE)->format('Y-m-d');
                $row[$date] = $record->JUMLAH_JAM_LEMBUR;
            }

            $row['total_kehadiran'] = $totalKehadiran;
            $row['1'] = $field1;
            $row['2'] = $field2;
            $row['lembur_khusus'] = $lemburKhusus;
            $row['total'] = $field1 + $field2;

            foreach ($lemburCharacterGroup as $key => $value) {
                $row[$key] = $value->count();
            }

            return $row;
        })->values();
        
        return response()->json(['data' => $pivoted]);
    }
}
