<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceFingerController;
use App\Http\Controllers\BiodataController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PelamarController;
use App\Http\Controllers\PKWTController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\AdminKunjunganController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\DokterAntrianController;
use App\Http\Controllers\EmployeePayrollController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InsentifMasterController;
use App\Http\Controllers\PayrollComponentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollMasterController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PayrollProcessController;
use App\Models\PayrollComponent;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [HomeController::class, 'index'])->name('/');
// Route::get('/template/auditsewing', [TemplateController::class, 'auditsewing'])->name('template.auditsewing');
// Route::get('/template/auditnonsewing', [TemplateController::class, 'auditnonsewing'])->name('template.auditnonsewing');

// Route::group(['middleware' => 'guest'], function () {
// Route::get('/register', [RegisterController::class, 'index'])->name('register');
Route::post('/register/guest', [RegisterController::class, 'store'])->name('register.guest');

Route::get('/login', [LoginController::class, 'login'])->name('login.guest');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login');
// });

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [HomeController::class, 'home'])->name('home');
    Route::get('/home/get-pkwt-chart', [HomeController::class, 'getPKWTChart'])->name('home.get-pkwt-chart');
    Route::get('/home/get-recap-count', [HomeController::class, 'getRecapCount'])->name('home.get-recap-count');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    //Register
    Route::get('/register/create', [RegisterController::class, 'create'])->name('register.create')->middleware(['auth', 'role:Admin']);
    Route::post('/register', [RegisterController::class, 'storeAuth'])->name('register')->middleware(['auth', 'role:Admin']);

    // PKWT
    Route::get('/pkwt/index', [PKWTController::class, 'index'])->name('pkwt.index')->middleware(['auth', 'role:Admin|HRD']);


    // BIODATA
    Route::get('/biodata/index', [BiodataController::class, 'index'])->name('biodata.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/biodata/get-data', [BiodataController::class, 'getData'])->name('biodata.get-data')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/biodata/fetch-last-npk', [BiodataController::class, 'fetchLastNpk'])->name('biodata.fetch-last-npk')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/biodata/store', [BiodataController::class, 'store'])->name('biodata.store')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/biodata/edit/{NPK}', [BiodataController::class, 'edit'])->name('biodata.edit')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/biodata/update/{NPK}', [BiodataController::class, 'update'])->name('biodata.update')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/biodata/show/{NPK}', [BiodataController::class, 'show'])->name('biodata.show')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/biodata/update-photo/{NPK}', [BiodataController::class, 'updatePhoto'])->name('biodata.update-photo')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/biodata/exit/{NPK}', [BiodataController::class, 'exit'])->name('biodata.exit')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/biodata/export', [BiodataController::class, 'export'])->name('biodata.export')->middleware(['auth', 'role:Admin|HRD']);

    // PKWT
    Route::get('/pkwt/index', [PKWTController::class, 'index'])->name('pkwt.index')->middleware(['auth', 'role:Admin|HRD']);

    // Pelamar
    Route::get('/pelamar/index', [PelamarController::class, 'index'])->name('pelamar.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/pelamar/import', [PelamarController::class, 'import'])->name('pelamar.import')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/pelamar/assign', [PelamarController::class, 'assign'])->name('pelamar.assign')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/pelamar/detail/{id}', [PelamarController::class, 'detail'])->name('pelamar.detail')->middleware(['auth', 'role:Admin|HRD']);


    //Role
    Route::get('/role/index', [RoleController::class, 'index'])->name('role.index')->middleware(['auth', 'role:Admin']);
    Route::get('/role/delete/{id}', [RoleController::class, 'delete'])->name('role.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/role/create', [RoleController::class, 'create'])->name('role.create')->middleware(['auth', 'role:Admin']);
    Route::post('/role/store', [RoleController::class, 'store'])->name('role.store')->middleware(['auth', 'role:Admin']);
    Route::get('/role/find/{id}', [RoleController::class, 'find'])->name('role.find')->middleware(['auth', 'role:Admin']);
    Route::post('/role/update', [RoleController::class, 'update'])->name('role.update')->middleware(['auth', 'role:Admin']);

    //User
    Route::get('/user/index', [UserController::class, 'index'])->name('user.index')->middleware(['auth', 'role:Admin']);
    Route::get('/user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::post('/user/update', [UserController::class, 'update'])->name('user.update')->middleware(['auth', 'role:Admin']);
    Route::get('/user/detail/{id}', [UserController::class, 'detail'])->name('user.detail')->middleware(['auth', 'role:Admin']);
    Route::get('/user/delete/{id}', [UserController::class, 'delete'])->name('user.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/user/assign/{id}', [UserController::class, 'assign'])->name('user.assign')->middleware(['auth', 'role:Admin']);
    Route::post('/user/assignrole', [UserController::class, 'assignrole'])->name('user.assignrole')->middleware(['auth', 'role:Admin']);

    // Payroll Component
    Route::get('/payroll-components/index', [PayrollComponentController::class, 'index'])->name('payroll-components.index')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-components/create', [PayrollComponentController::class, 'create'])->name('payroll-components.create')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-components/store', [PayrollComponentController::class, 'store'])->name('payroll-components.store')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-components/detail/{id}', [PayrollComponentController::class, 'detail'])->name('payroll-components.detail')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-components/edit/{id}', [PayrollComponentController::class, 'edit'])->name('payroll-components.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-components/update', [PayrollComponentController::class, 'update'])->name('payroll-components.update')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-components/delete/{id}', [PayrollComponentController::class, 'delete'])->name('payroll-components.delete')->middleware(['auth', 'role:Admin']);

    // Payroll Period
    Route::get('/payroll-periods/index', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-periods/create', [PayrollPeriodController::class, 'create'])->name('payroll-periods.create')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-periods/store', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-periods/detail/{id}', [PayrollPeriodController::class, 'detail'])->name('payroll-periods.detail')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-periods/edit/{id}', [PayrollPeriodController::class, 'edit'])->name('payroll-periods.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-periods/update', [PayrollPeriodController::class, 'update'])->name('payroll-periods.update')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-periods/delete/{id}', [PayrollPeriodController::class, 'delete'])->name('payroll-periods.delete')->middleware(['auth', 'role:Admin']);

    // Payroll Process
    Route::get('/payroll-process/index', [PayrollProcessController::class, 'index'])->name('payroll-process.index');
    Route::get('/payroll-process/generate', [PayrollProcessController::class, 'generate'])->name('payroll-process.generate');
    // Route::post('/payroll-process/process', [PayrollProcessController::class, 'process'])->name('payroll-process.process');
    Route::get('/payroll-process/details/{id}', [PayrollProcessController::class, 'details'])->name('payroll-process.details');
    Route::delete('/payroll-process/delete/{period_id}', [PayrollProcessController::class, 'destroy'])->name('payroll-process.destroy');
    Route::get('/payroll-process/edit/{id}', [PayrollProcessController::class, 'edit'])->name('payroll-process.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-process/update', [PayrollProcessController::class, 'update'])->name('payroll-process.update')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-slip/{run_id}/{npk}', [PayrollProcessController::class, 'slip'])->name('payroll-slip');
    Route::get('payroll-process/export-rekap/{run_id}', [PayrollProcessController::class, 'exportRekap'])->name('payroll.export.rekap');
    Route::get('/payroll/export/{run_id}', [PayrollProcessController::class, 'export'])->name('payroll.export.export');
    Route::get('/payroll/export-progress/{id}', [PayrollProcessController::class, 'progress'])->name('payroll.export.progress');
    Route::get('/payroll-slip/view/{run_id}/{npk}', [PayrollProcessController::class, 'passwordForm']);

    //Payroll Master
    Route::get('/payroll-master', [PayrollMasterController::class, 'index'])->name('payroll-master.index');
    Route::get('/payroll-master/create', [PayrollMasterController::class, 'create'])->name('payroll-master.create');
    Route::get('/payroll-master/edit/{id}', [PayrollMasterController::class, 'edit'])->name('payroll-master.edit');
    Route::get('/payroll-master/delete/{id}', [PayrollMasterController::class, 'delete'])->name('payroll-master.delete');
    Route::post('/payroll-master/import', [PayrollMasterController::class, 'import'])->name('payroll-master.import');
    Route::get('/payroll-master/template', [PayrollMasterController::class, 'template'])->name('payroll-master.template');

    // Holiday
    Route::get('holidays/sync', [HolidayController::class, 'sync'])->name('holidays.sync');
    Route::get('holidays/index', [HolidayController::class, 'index'])->name('holidays.index');
    Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
    Route::post('holidays/store', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/delete/{id}', [HolidayController::class, 'delete'])->name('holidays.delete');
    Route::get('/holidays/edit/{id}', [HolidayController::class, 'edit'])->name('holidays.edit');
    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
    Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export');

    // Insentif
    Route::get('insentif-master/index', [InsentifMasterController::class, 'index'])->name('insentif-master.index');
    Route::get('insentif-master/create', [InsentifMasterController::class, 'create'])->name('insentif-master.create');
    Route::post('insentif-master/store', [InsentifMasterController::class, 'store'])->name('insentif-master.store');
    Route::delete('insentif-master/delete/{id}', [InsentifMasterController::class, 'destroy'])->name('insentif-master.delete');
    Route::get('/insentif-master/edit/{id}', [InsentifMasterController::class, 'edit'])->name('insentif-master.edit');
    Route::post('insentif-master/import', [InsentifMasterController::class, 'import'])->name('insentif-master.import');
    Route::get('insentif-master/export', [InsentifMasterController::class, 'export'])->name('insentif-master.export');
    Route::get('/insentif-master/template', [InsentifMasterController::class, 'template'])->name('insentif-master.template');
    Route::post('/insentif-master/import', [InsentifMasterController::class, 'import'])->name('insentif-master.import');

    // Holiday
    Route::get('holidays/index', [HolidayController::class, 'index'])->name('holidays.index');
    Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
    Route::post('holidays/store', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/delete/{id}', [HolidayController::class, 'delete'])->name('holidays.delete');
    Route::get('/holidays/edit/{id}', [HolidayController::class, 'edit'])->name('holidays.edit');
    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
    Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export');

    // attendance finger
    Route::get('/attendance-finger/index', [AttendanceFingerController::class, 'index'])->name('attendance-finger.index');
    Route::post('/attendance-finger/sync', [AttendanceFingerController::class, 'sync'])->name('attendance-finger.sync');
    Route::post('/attendance-finger/export', [AttendanceFingerController::class, 'export'])->name('attendance-finger.export');

    //Attendance
    Route::get('/attendance/index', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/import', [AttendanceController::class, 'import'])->name('attendance.import');
    Route::post('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::get('/attendance/export_view', [AttendanceController::class, 'export_view'])->name('attendance.export_view');
    Route::post('/attendance/deleteAll', [AttendanceController::class, 'deleteAll'])->name('attendance.deleteAll');
    Route::post('/attendance/auditsewing', [AttendanceController::class, 'auditsewing'])->name('attendance.auditsewing');
    Route::post('/attendance/auditnonsewing', [AttendanceController::class, 'auditnonsewing'])->name('attendance.auditnonsewing');
    Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
    Route::get('/attendance/check-master-data', [AttendanceController::class, 'checkMasterData'])->name('attendance.checkMasterData');
    Route::get('/attendance/edit/{id}', [AttendanceController::class, 'edit'])->name('attendance.edit');
    Route::post('/attendance/update/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::get('/attendance/showAttendance', [AttendanceController::class, 'showAttendance'])->name('attendance.showAttendance');

    // Overtime
    Route::get('/overtime', [OvertimeController::class, 'index'])->name('overtime.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/download-template', [OvertimeController::class, 'downloadTemplateOvertime'])->name('overtime.downloadTemplate')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/overtime/import', [OvertimeController::class, 'importOvertime'])->name('overtime.import')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/get-data', [OvertimeController::class, 'getData'])->name('overtime.get-data')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/calendar-data', [OvertimeController::class, 'calendarDisplay'])->name('overtime.calendar-data')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/calendar', [OvertimeController::class, 'calendarOvertime'])->name('overtime.calendar')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/export', [OvertimeController::class, 'exportCalendar'])->name('overtime.export')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/overtime/update/{id}', [OvertimeController::class, 'update'])->name('overtime.update')->middleware(['auth', 'role:Admin|HRD']);
    Route::delete('/overtime/delete/{id}', [OvertimeController::class, 'destroy'])->name('overtime.destroy')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/overtime/delete-all', [OvertimeController::class, 'destroyAll'])->name('overtime.destroyAll')->middleware(['auth', 'role:Admin|HRD']);

    // ========================================
    // POLIKLINIK — Admin Kunjungan
    // ========================================
    Route::prefix('kunjungan')->middleware('role:Admin|Dokter')->group(function () {
        Route::get('/', [AdminKunjunganController::class, 'index'])->name('kunjungan.index');
        Route::get('/get-data', [AdminKunjunganController::class, 'getData'])->name('kunjungan.get-data');
        Route::post('/store', [AdminKunjunganController::class, 'store'])->name('kunjungan.store');
        Route::get('/cetak-kartu/{id}', [AdminKunjunganController::class, 'cetakKartu'])->name('kunjungan.cetak-kartu');
        Route::get('/search-karyawan', [AdminKunjunganController::class, 'searchKaryawan'])->name('kunjungan.search-karyawan');
    });

    // ========================================
    // POLIKLINIK — Report
    // ========================================
    Route::prefix('report-poliklinik')->middleware('role:Admin|Dokter')->group(function () {
        Route::get('/kartu-berobat/{npk}', [AdminReportController::class, 'kartuBerobat'])->name('report.kartu-berobat');
        Route::get('/rekap', [AdminReportController::class, 'rekap'])->name('report.rekap');
    });

    // ========================================
    // POLIKLINIK — Dokter Antrian
    // ========================================
    Route::prefix('dokter')->middleware('role:Dokter|Admin')->group(function () {
        Route::get('/antrian', [DokterAntrianController::class, 'index'])->name('dokter.antrian');
        Route::post('/mulai-periksa/{id}', [DokterAntrianController::class, 'mulaiPeriksa'])->name('dokter.mulai-periksa');
        Route::get('/periksa/{id}', [DokterAntrianController::class, 'formPeriksa'])->name('dokter.periksa');
        Route::post('/selesai-periksa/{id}', [DokterAntrianController::class, 'selesaiPeriksa'])->name('dokter.selesai-periksa');
    });
});

// Payroll 
Route::get('/payroll/calculate', [PayrollController::class, 'calculate'])->name('payroll.calculate');
Route::get('/payroll-process/process', [PayrollProcessController::class, 'process'])->name('payroll-process.process');

// Employee Payroll
Route::post('/employee-payroll/{npk}/show-slip', [EmployeePayrollController::class, 'showSlip'])->name('employee-payroll.show-slip');
Route::post('/employee-payroll/view', [EmployeePayrollController::class, 'verifyPassword'])->name('employee-payroll.verify-password');
Route::get('/employee-payroll', [EmployeePayrollController::class, 'index'])->name('employee-payroll.index');
Route::get('/employee-payroll/qr-login', [EmployeePayrollController::class, 'qrLogin']);
Route::get('/employee-payroll/view', [EmployeePayrollController::class, 'verifyPassword'])->name('employee-payroll.verify-password');
Route::get('/employee-payroll/show/{run_id}/{npk}', [EmployeePayrollController::class, 'showSlip'])->name('view-slip');
