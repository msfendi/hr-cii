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
use App\Http\Controllers\DeptController;
use App\Http\Controllers\AdminKunjunganController;
use App\Http\Controllers\AdminLeaveBalanceController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\ApplicantContactController;
use App\Http\Controllers\CuttingInsentifMasterController;
use App\Http\Controllers\DokterAntrianController;
use App\Http\Controllers\PengajuanCutiController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\LeaveApprovalController;
use App\Http\Controllers\EmployeePayrollController;
use App\Http\Controllers\EmployeeShiftController;
use App\Http\Controllers\EmployeeThrController;
use App\Http\Controllers\EvaluationEmployeeController;
use App\Http\Controllers\EvaluationJobscopeController;
use App\Http\Controllers\EvaluationQuestionnaireController;
use App\Http\Controllers\ExpatController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InsentifApprovalController;
use App\Http\Controllers\InsentifMasterController;
use App\Http\Controllers\InsentifThresholdController;
use App\Http\Controllers\LineInsentifMasterController;
use App\Http\Controllers\PadInsentifMasterController;
use App\Http\Controllers\PayrollAdjusmentController;
use App\Http\Controllers\PayrollApproveController;
use App\Http\Controllers\PayrollComponentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollMasterController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PayrollProcessController;
use App\Http\Controllers\PayrollSettingController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ThrApproveController;
use App\Http\Controllers\ThrPeriodController;
use App\Http\Controllers\ThrProcessController;
use App\Http\Controllers\WhatsappDeviceController;
use App\Http\Controllers\WhatsappSendController;
use App\Http\Controllers\WhatsappTemplateController;
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

Route::group(['middleware' => 'guest'], function () {
    // Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register/guest', [RegisterController::class, 'store'])->name('register.guest');

    Route::get('/login', [LoginController::class, 'login'])->name('login.guest');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login');
    Route::get('/login/qrauth', [LoginController::class, 'qrauth'])->name('login.qrauth');
});

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
    Route::get('/payroll-process/index', [PayrollProcessController::class, 'index'])->name('payroll-process.index')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-process/generate', [PayrollProcessController::class, 'generate'])->name('payroll-process.generate')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-process/process', [PayrollProcessController::class, 'process'])->name('payroll-process.process')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-process/details/{id}', [PayrollProcessController::class, 'details'])->name('payroll-process.details')->middleware(['auth', 'role:Admin']);
    Route::delete('/payroll-process/delete/{period_id}', [PayrollProcessController::class, 'destroy'])->name('payroll-process.destroy')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-process/edit/{id}', [PayrollProcessController::class, 'edit'])->name('payroll-process.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-process/update', [PayrollProcessController::class, 'update'])->name('payroll-process.update')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-slip/{run_id}/{npk}', [PayrollProcessController::class, 'slip'])->name('payroll-slip')->middleware(['auth', 'role:Admin']);
    Route::get('payroll-process/export-rekap/{run_id}', [PayrollProcessController::class, 'exportRekap'])->name('payroll.export.rekap')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll/export/{run_id}', [PayrollProcessController::class, 'export'])->name('payroll.export.export')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll/export-progress/{id}', [PayrollProcessController::class, 'progress'])->name('payroll.export.progress')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-slip/view/{run_id}/{npk}', [PayrollProcessController::class, 'passwordForm'])->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-process/approval/{period}', [PayrollProcessController::class, 'approvalStatus'])->middleware(['auth', 'role:Admin']);

    //Payroll Master
    Route::get('/payroll-master', [PayrollMasterController::class, 'index'])->name('payroll-master.index')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-master/create', [PayrollMasterController::class, 'create'])->name('payroll-master.create')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-master', [PayrollMasterController::class, 'index'])->name('payroll-master.index')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-master/edit/{id}', [PayrollMasterController::class, 'edit'])->name('payroll-master.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-master/update/{id}', [PayrollMasterController::class, 'update'])->name('payroll-master.update')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-master/delete/{id}', [PayrollMasterController::class, 'delete'])->name('payroll-master.delete')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-master/store', [PayrollMasterController::class, 'store'])->name('payroll-master.store')->middleware(['auth', 'role:Admin']);
    Route::post('/payroll-master/import', [PayrollMasterController::class, 'import'])->name('payroll-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('/payroll-master/template', [PayrollMasterController::class, 'template'])->name('payroll-master.template')->middleware(['auth', 'role:Admin']);


    // Thr Period
    Route::get('/thr-periods/index', [ThrPeriodController::class, 'index'])->name('thr-periods.index')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-periods/create', [ThrPeriodController::class, 'create'])->name('thr-periods.create')->middleware(['auth', 'role:Admin']);
    Route::post('/thr-periods/store', [ThrPeriodController::class, 'store'])->name('thr-periods.store')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-periods/detail/{id}', [ThrPeriodController::class, 'detail'])->name('thr-periods.detail')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-periods/edit/{id}', [ThrPeriodController::class, 'edit'])->name('thr-periods.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/thr-periods/update', [ThrPeriodController::class, 'update'])->name('thr-periods.update')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-periods/delete/{id}', [ThrPeriodController::class, 'delete'])->name('thr-periods.delete')->middleware(['auth', 'role:Admin']);

    // Thr Process
    Route::get('/thr-process/index', [ThrProcessController::class, 'index'])->name('thr-process.index')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-process/generate', [ThrProcessController::class, 'generate'])->name('thr-process.generate')->middleware(['auth', 'role:Admin']);
    Route::post('/thr-process/process', [ThrProcessController::class, 'process'])->name('thr-process.process')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-process/details/{id}', [ThrProcessController::class, 'details'])->name('thr-process.details')->middleware(['auth', 'role:Admin']);
    Route::delete('/thr-process/delete/{period_id}', [ThrProcessController::class, 'destroy'])->name('thr-process.destroy')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-process/edit/{id}', [ThrProcessController::class, 'edit'])->name('thr-process.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/thr-process/update', [ThrProcessController::class, 'update'])->name('thr-process.update')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-slip/{run_id}/{npk}', [ThrProcessController::class, 'slip'])->name('thr-slip')->middleware(['auth', 'role:Admin']);
    Route::get('thr-process/export-rekap/{run_id}', [ThrProcessController::class, 'exportRekap'])->name('thr.export.rekap')->middleware(['auth', 'role:Admin']);
    Route::get('/thr/export/{run_id}', [ThrProcessController::class, 'export'])->name('thr.export.export')->middleware(['auth', 'role:Admin']);
    Route::get('/thr/export-progress/{id}', [ThrProcessController::class, 'progress'])->name('thr.export.progress')->middleware(['auth', 'role:Admin']);
    Route::get('/thr-slip/view/{run_id}/{npk}', [ThrProcessController::class, 'passwordForm'])->middleware(['auth', 'role:Admin']);
    Route::get('/thr-process/approval/{period}', [ThrProcessController::class, 'approvalStatus'])->middleware(['auth', 'role:Admin']);

    // Evaluation Questionnaire
    Route::prefix('evaluation-questionnaire')->group(function () {
        Route::get('/', [EvaluationQuestionnaireController::class, 'index'])->name('evaluation-questionnaire.index')->middleware(['auth', 'role:Admin|HRD']);
        Route::post('/import', [EvaluationQuestionnaireController::class, 'import'])->name('evaluation-questionnaire.import')->middleware(['auth', 'role:Admin|HRD']);
        Route::get('/template', [EvaluationQuestionnaireController::class, 'template'])->name('evaluation-questionnaire.template')->middleware(['auth', 'role:Admin|HRD']);
        Route::get('/delete/{id}', [EvaluationQuestionnaireController::class, 'delete'])->name('evaluation-questionnaire.delete')->middleware(['auth', 'role:Admin|HRD']);
    });

    // Payroll Approve
    Route::post('/payroll-approve/{id}/approve', [PayrollApproveController::class, 'approve'])->name('payroll-approve.approve')->middleware(['auth', 'role:Admin']);
    Route::prefix('payroll-approve')->group(function () {
        Route::get('/', [PayrollApproveController::class, 'index'])->name('payroll-approve.index')->middleware(['auth', 'role:Admin']);
        Route::post('/create/{payroll_run_id}', [PayrollApproveController::class, 'store'])->name('payroll-approve.create')->middleware(['auth', 'role:Admin']);
        Route::post('/{id}/approve', [PayrollApproveController::class, 'approve'])->name('payroll-approve.approve')->middleware(['auth', 'role:Admin']);
    });



    // Thr Approve
    Route::post('/thr-approve/{id}/approve', [ThrApproveController::class, 'approve'])->name('thr-approve.approve')->middleware(['auth', 'role:Admin']);
    Route::prefix('thr-approve')->group(function () {
        Route::get('/', [ThrApproveController::class, 'index'])->name('thr-approve.index')->middleware(['auth', 'role:Admin']);
        Route::post('/create/{thr_run_id}', [ThrApproveController::class, 'store'])->name('thr-approve.create')->middleware(['auth', 'role:Admin']);
        Route::post('/{id}/approve', [ThrApproveController::class, 'approve'])->name('thr-approve.approve')->middleware(['auth', 'role:Admin']);
    });


    // Insentif Approve
    Route::get('/insentif-approve', [InsentifApprovalController::class, 'index'])->name('insentif-approve.index')->middleware(['auth', 'role:Admin']);
    Route::post('/insentif-approve/{id}/approve', [InsentifApprovalController::class, 'approve'])->name('insentif-approve.approve')->middleware(['auth', 'role:Admin']);
    Route::get('/insentif-approve/{id}/detail', [InsentifApprovalController::class, 'detail'])->name('insentif-approve.detail')->middleware(['auth', 'role:Admin']);

    Route::prefix('payroll-setting')->group(function () {
        Route::get('/', [PayrollSettingController::class, 'index'])->name('payroll-setting.index')->middleware(['auth', 'role:Admin']);
        Route::post('/store', [PayrollSettingController::class, 'store'])->name('payroll-setting.store')->middleware(['auth', 'role:Admin']);
        Route::get('/edit/{id}', [PayrollSettingController::class, 'edit'])->name('payroll-setting.edit')->middleware(['auth', 'role:Admin']);
        Route::put('/update/{id}', [PayrollSettingController::class, 'update'])->name('payroll-setting.update')->middleware(['auth', 'role:Admin']);
        Route::delete('/delete/{id}', [PayrollSettingController::class, 'delete'])->name('payroll-setting.delete')->middleware(['auth', 'role:Admin']);
    });

    // Evaluation Jobscope
    Route::prefix('evaluation-jobscope')->group(function () {
        Route::get('/', [EvaluationJobscopeController::class, 'index'])->name('evaluation-jobscope.index')->middleware(['auth', 'role:Admin|HRD']);
        Route::get('/delete/{id}', [EvaluationJobscopeController::class, 'delete'])->name('evaluation-jobscope.delete')->middleware(['auth', 'role:Admin|HRD']);
    });

    //  Evaluation Employee
    Route::get('/evaluation-employee', [EvaluationEmployeeController::class, 'index'])->name('evaluation-employee.index')->middleware(['auth', 'role:Admin|HRD']);

    // Holiday
    Route::get('holidays/sync', [HolidayController::class, 'sync'])->name('holidays.sync')->middleware(['auth', 'role:Admin']);
    Route::get('holidays/index', [HolidayController::class, 'index'])->name('holidays.index')->middleware(['auth', 'role:Admin']);
    Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create')->middleware(['auth', 'role:Admin']);
    Route::post('holidays/store', [HolidayController::class, 'store'])->name('holidays.store')->middleware(['auth', 'role:Admin']);
    Route::get('holidays/delete/{id}', [HolidayController::class, 'destroy'])->name('holidays.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/holidays/edit/{id}', [HolidayController::class, 'edit'])->name('holidays.edit')->middleware(['auth', 'role:Admin']);
    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import')->middleware(['auth', 'role:Admin']);
    Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export')->middleware(['auth', 'role:Admin']);

    // Line Insentif Master
    Route::get('line-insentif-master/index', [LineInsentifMasterController::class, 'index'])->name('line-insentif-master.index')->middleware(['auth', 'role:Admin']);
    Route::get('line-insentif-master/create', [LineInsentifMasterController::class, 'create'])->name('line-insentif-master.create')->middleware(['auth', 'role:Admin']);
    Route::post('line-insentif-master/store', [LineInsentifMasterController::class, 'store'])->name('line-insentif-master.store')->middleware(['auth', 'role:Admin']);
    Route::delete('line-insentif-master/delete/{id}', [LineInsentifMasterController::class, 'destroy'])->name('line-insentif-master.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/line-insentif-master/edit/{id}', [LineInsentifMasterController::class, 'edit'])->name('line-insentif-master.edit')->middleware(['auth', 'role:Admin']);
    Route::post('line-insentif-master/import', [LineInsentifMasterController::class, 'import'])->name('line-insentif-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('line-insentif-master/export', [LineInsentifMasterController::class, 'export'])->name('line-insentif-master.export')->middleware(['auth', 'role:Admin']);
    Route::get('/line-insentif-master/template', [LineInsentifMasterController::class, 'template'])->name('line-insentif-master.template')->middleware(['auth', 'role:Admin']);
    Route::post('/line-insentif-master/import', [LineInsentifMasterController::class, 'import'])->name('line-insentif-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('/line-insentif-master/{period}/check', [LineInsentifMasterController::class, 'check'])->name('line-insentif-master.check')->middleware(['auth', 'role:Admin']);

    // Cutting Insentif Master
    Route::get('cutting-insentif-master/index', [CuttingInsentifMasterController::class, 'index'])->name('cutting-insentif-master.index')->middleware(['auth', 'role:Admin']);
    Route::get('cutting-insentif-master/create', [CuttingInsentifMasterController::class, 'create'])->name('cutting-insentif-master.create')->middleware(['auth', 'role:Admin']);
    Route::post('cutting-insentif-master/store', [CuttingInsentifMasterController::class, 'store'])->name('cutting-insentif-master.store')->middleware(['auth', 'role:Admin']);
    Route::delete('cutting-insentif-master/delete/{id}', [CuttingInsentifMasterController::class, 'destroy'])->name('cutting-insentif-master.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/cutting-insentif-master/edit/{id}', [CuttingInsentifMasterController::class, 'edit'])->name('cutting-insentif-master.edit')->middleware(['auth', 'role:Admin']);
    Route::post('cutting-insentif-master/import', [CuttingInsentifMasterController::class, 'import'])->name('cutting-insentif-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('cutting-insentif-master/export', [CuttingInsentifMasterController::class, 'export'])->name('cutting-insentif-master.export')->middleware(['auth', 'role:Admin']);
    Route::get('/cutting-insentif-master/template', [CuttingInsentifMasterController::class, 'template'])->name('cutting-insentif-master.template')->middleware(['auth', 'role:Admin']);
    Route::post('/cutting-insentif-master/import', [CuttingInsentifMasterController::class, 'import'])->name('cutting-insentif-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('/cutting-insentif-master/{period}/check', [CuttingInsentifMasterController::class, 'check'])->name('cutting-insentif-master.check')->middleware(['auth', 'role:Admin']);

    // Pad Print Insentif Master
    Route::get('pad-insentif-master/index', [PadInsentifMasterController::class, 'index'])->name('pad-insentif-master.index')->middleware(['auth', 'role:Admin']);
    Route::get('pad-insentif-master/create', [PadInsentifMasterController::class, 'create'])->name('pad-insentif-master.create')->middleware(['auth', 'role:Admin']);
    Route::post('pad-insentif-master/store', [PadInsentifMasterController::class, 'store'])->name('pad-insentif-master.store')->middleware(['auth', 'role:Admin']);
    Route::delete('pad-insentif-master/delete/{id}', [PadInsentifMasterController::class, 'destroy'])->name('pad-insentif-master.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/pad-insentif-master/edit/{id}', [PadInsentifMasterController::class, 'edit'])->name('pad-insentif-master.edit')->middleware(['auth', 'role:Admin']);
    Route::post('pad-insentif-master/import', [PadInsentifMasterController::class, 'import'])->name('pad-insentif-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('pad-insentif-master/export', [PadInsentifMasterController::class, 'export'])->name('pad-insentif-master.export')->middleware(['auth', 'role:Admin']);
    Route::get('/pad-insentif-master/template', [PadInsentifMasterController::class, 'template'])->name('pad-insentif-master.template')->middleware(['auth', 'role:Admin']);
    Route::post('/pad-insentif-master/import', [PadInsentifMasterController::class, 'import'])->name('pad-insentif-master.import')->middleware(['auth', 'role:Admin']);
    Route::get('/pad-insentif-master/{period}/check', [PadInsentifMasterController::class, 'check'])->name('pad-insentif-master.check')->middleware(['auth', 'role:Admin']);

    Route::prefix('payroll-adjusments')->group(function () {
        Route::get('/', [PayrollAdjusmentController::class, 'index'])->name('payroll-adjusments.index');
        Route::get('/create', [PayrollAdjusmentController::class, 'create'])->name('payroll-adjusments.create');
        Route::post('/store', [PayrollAdjusmentController::class, 'store'])->name('payroll-adjusments.store');
        Route::get('/edit/{id}', [PayrollAdjusmentController::class, 'edit'])->name('payroll-adjusments.edit');
        Route::post('/update/{id}', [PayrollAdjusmentController::class, 'update'])->name('payroll-adjusments.update');
        Route::delete('/delete/{id}', [PayrollAdjusmentController::class, 'destroy'])->name('payroll-adjusments.destroy');
    });

    // attendance finger
    Route::get('/attendance-finger/index', [AttendanceFingerController::class, 'index'])->name('attendance-finger.index');
    Route::post('/attendance-finger/sync', [AttendanceFingerController::class, 'sync'])->name('attendance-finger.sync');
    Route::post('/attendance-finger/export', [AttendanceFingerController::class, 'export'])->name('attendance-finger.export');
    Route::get('/attendance-finger/not-finger', [AttendanceFingerController::class, 'notFinger'])->name('attendance-finger.not-finger');
    Route::post('/attendance-finger/export-not-finger', [AttendanceFingerController::class, 'exportNotFinger'])->name('attendance-finger.export-not-finger');

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

    // DEPT
    Route::get('/dept/index', [DeptController::class, 'index'])->name('dept.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/dept/get-data', [DeptController::class, 'getData'])->name('dept.get-data')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/dept/store', [DeptController::class, 'store'])->name('dept.store')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/dept/update/{id}', [DeptController::class, 'update'])->name('dept.update')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/dept/destroy/{id}', [DeptController::class, 'destroy'])->name('dept.destroy')->middleware(['auth', 'role:Admin|HRD']);

    // Overtime
    Route::get('/overtime', [OvertimeController::class, 'index'])->name('overtime.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/download-template', [OvertimeController::class, 'downloadTemplateOvertime'])->name('overtime.downloadTemplate')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/overtime/import', [OvertimeController::class, 'importOvertime'])->name('overtime.import')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/get-data', [OvertimeController::class, 'getData'])->name('overtime.get-data')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/calendar-data', [OvertimeController::class, 'calendarDisplay'])->name('overtime.calendar-data')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/calendar', [OvertimeController::class, 'calendarOvertime'])->name('overtime.calendar')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/export', [OvertimeController::class, 'exportCalendar'])->name('overtime.export')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/overtime/export-template', [OvertimeController::class, 'exportCalendarTemplate'])->name('overtime.export-template')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/overtime/update/{id}', [OvertimeController::class, 'update'])->name('overtime.update')->middleware(['auth', 'role:Admin|HRD']);
    Route::delete('/overtime/delete/{id}', [OvertimeController::class, 'destroy'])->name('overtime.destroy')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/overtime/delete-all', [OvertimeController::class, 'destroyAll'])->name('overtime.destroyAll')->middleware(['auth', 'role:Admin|HRD']);

    // ========================================
    // APPROVAL RULES (Master)
    // ========================================
    Route::prefix('approval')->middleware('role:Admin|HRD')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('approval.index');
        Route::get('/get-data', [ApprovalController::class, 'getData'])->name('approval.get-data');
        Route::get('/search-employee', [ApprovalController::class, 'searchEmployee'])->name('approval.search-employee');
        Route::post('/store', [ApprovalController::class, 'store'])->name('approval.store');
        Route::post('/update/{id}', [ApprovalController::class, 'update'])->name('approval.update');
        Route::post('/destroy/{id}', [ApprovalController::class, 'destroy'])->name('approval.destroy');

        // Nested Rule inside Group
        Route::post('/rule/store', [ApprovalController::class, 'storeRule'])->name('approval.rule.store');
        Route::post('/rule/update/{id}', [ApprovalController::class, 'updateRule'])->name('approval.rule.update');
        Route::post('/rule/destroy/{id}', [ApprovalController::class, 'destroyRule'])->name('approval.rule.destroy');
    });

    // ========================================
    // LEAVE RECAP (Admin)
    // ========================================
    Route::prefix('leave-recap')->middleware('role:Admin|HRD')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminLeaveRecapController::class, 'index'])->name('leave-recap.index');
        Route::get('/get-data', [\App\Http\Controllers\AdminLeaveRecapController::class, 'getData'])->name('leave-recap.get-data');
        Route::get('/detail/{token}', [\App\Http\Controllers\AdminLeaveRecapController::class, 'getDetail'])->name('leave-recap.detail');
    });

    // ========================================
    // LEAVE BALANCES (Admin)
    // ========================================
    Route::prefix('leave-balances')->middleware('role:Admin|HRD')->group(function () {
        Route::get('/', [AdminLeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::get('/get-data', [AdminLeaveBalanceController::class, 'getData'])->name('leave-balances.get-data');
        Route::get('/show/{NPK}', [AdminLeaveBalanceController::class, 'show'])->name('leave-balances.show');
        Route::post('/store', [AdminLeaveBalanceController::class, 'storeBalance'])->name('leave-balances.store');
        Route::post('/update/{id}', [AdminLeaveBalanceController::class, 'updateBalance'])->name('leave-balances.update');
        Route::delete('/destroy/{id}', [AdminLeaveBalanceController::class, 'destroyBalance'])->name('leave-balances.destroy');

        Route::post('/generate-yearly', [AdminLeaveBalanceController::class, 'generateYearlyBalance'])->name('leave-balances.generate-yearly');
    });

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

    // Recruitment
    Route::get('/recruitment/index', [RecruitmentController::class, 'index'])->name('recruitment.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/recruitment/send-whatsapp', [RecruitmentController::class, 'sendWhatsApp'])->name('recruitment.sendWhatsApp')->middleware(['auth', 'role:Admin|HRD']);

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP DEVICE
    |--------------------------------------------------------------------------
    */

    Route::get('/devices', [WhatsappDeviceController::class, 'index'])->name('devices.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/devices/create', [WhatsappDeviceController::class, 'create'])->name('devices.create')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/devices/store', [WhatsappDeviceController::class, 'store'])->name('devices.store')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/devices/edit/{id}', [WhatsappDeviceController::class, 'edit'])->name('devices.edit')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/devices/update/{id}', [WhatsappDeviceController::class, 'update'])->name('devices.update')->middleware(['auth', 'role:Admin|HRD']);
    Route::delete('/devices/destroy/{id}', [WhatsappDeviceController::class, 'destroy'])->name('devices.destroy')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/devices/status/{id}', [WhatsappDeviceController::class, 'checkStatus'])->name('devices.status')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('devices/{id}/qr', [WhatsappDeviceController::class, 'qr'])->name('devices.qr')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/devices/{device}/disconnect', [WhatsappDeviceController::class, 'disconnect'])->name('devices.disconnect')->middleware(['auth', 'role:Admin|HRD']);


    /*
    |--------------------------------------------------------------------------
    | WHATSAPP TEMPLATE
    |--------------------------------------------------------------------------
    */

    Route::get('/templates', [WhatsappTemplateController::class, 'index'])->name('templates.index')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/templates/create', [WhatsappTemplateController::class, 'create'])->name('templates.create')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/templates/store', [WhatsappTemplateController::class, 'store'])->name('templates.store')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/templates/edit/{id}', [WhatsappTemplateController::class, 'edit'])->name('templates.edit')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/templates/update/{id}', [WhatsappTemplateController::class, 'update'])->name('templates.update')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/templates/destroy/{id}', [WhatsappTemplateController::class, 'destroy'])->name('templates.destroy')->middleware(['auth', 'role:Admin|HRD']);

    /*
    |--------------------------------------------------------------------------
    | SEND WHATSAPP
    |--------------------------------------------------------------------------
    */

    Route::get('/send/create', [WhatsappSendController::class, 'create'])->name('send.create')->middleware(['auth', 'role:Admin|HRD']);
    Route::get('/send-whatsapp', [WhatsappSendController::class, 'create'])->name('send.create')->middleware(['auth', 'role:Admin|HRD']);
    Route::post('/send-template', [WhatsappSendController::class, 'sendTemplate'])->name('send-template')->middleware(['auth', 'role:Admin|HRD']);

    Route::get('/applicant-contact', [ApplicantContactController::class, 'index'])->name('applicant-contact.index');
    Route::get('/applicant-contact/send', [ApplicantContactController::class, 'send'])->name('applicant-contact.send');
    Route::get('/applicant-contact/create', [ApplicantContactController::class, 'create'])->name('applicant-contact.create');
    Route::post('/applicant-contact/store', [ApplicantContactController::class, 'store'])->name('applicant-contact.store');
    Route::delete('/applicant-contact/{id}', [ApplicantContactController::class, 'destroy'])->name('applicant-contact.destroy');

    Route::prefix('expat')->group(function () {

        Route::get('master/index', [ExpatController::class, 'indexMaster'])->name('expat.master.index');
        Route::get('master/create', [ExpatController::class, 'createMaster'])->name('expat.master.create');
        Route::get('master/edit/{id}', [ExpatController::class, 'editMaster'])->name('expat.master.edit');
        Route::post('master/delete/{id}', [ExpatController::class, 'deleteMaster'])->name('expat.master.delete');
        Route::post('master/store', [ExpatController::class, 'storeMaster'])->name('expat.master.store');
        Route::post('import/master', [ExpatController::class, 'importMaster'])->name('expat.import.master');
        Route::get('template/master', [ExpatController::class, 'templateMaster'])->name('expat.template.master');

        Route::get('onleave/index', [ExpatController::class, 'indexOnleave'])->name('expat.onleave.index');
        Route::get('onleave/create', [ExpatController::class, 'createOnLeave'])->name('expat.onleave.create');
        Route::post('onleave/store', [ExpatController::class, 'storeOnleave'])->name('expat.onleave.store');
        Route::get('onleave/edit/{id}', [ExpatController::class, 'editOnleave'])->name('expat.onleave.edit');
        Route::post('onleave/delete/{id}', [ExpatController::class, 'deleteOnleave'])->name('expat.onleave.delete');
        Route::post('import/onleave', [ExpatController::class, 'importOnleave'])->name('expat.import.onleave');
        Route::get('template/onleave', [ExpatController::class, 'templateOnleave'])->name('expat.template.onleave');

        Route::get('cost/index', [ExpatController::class, 'indexCost'])->name('expat.cost.index');
        Route::get('cost/create', [ExpatController::class, 'createCost'])->name('expat.cost.create');
        Route::post('cost/store', [ExpatController::class, 'storeCost'])->name('expat.cost.store');
        Route::get('cost/edit/{id}', [ExpatController::class, 'editCost'])->name('expat.cost.edit');
        Route::post('cost/delete/{id}', [ExpatController::class, 'deleteCost'])->name('expat.cost.delete');
        Route::post('import/cost', [ExpatController::class, 'importCost'])->name('expat.import.cost');
        Route::get('template/cost', [ExpatController::class, 'templateCost'])->name('expat.template.cost');

        Route::get('expat/rekap/export', [ExpatController::class, 'exportRekap'])->name('expat.rekap.export');
    });

    /*
    |--------------------------------------------------------------------------
    | SHIFT
    |--------------------------------------------------------------------------
    */

    Route::get('/shift', [ShiftController::class, 'index'])->name('shift.index');
    Route::get('/shift/create', [ShiftController::class, 'create'])->name('shift.create');
    Route::post('/shift/store', [ShiftController::class, 'store'])->name('shift.store');
    Route::get('/shift/edit/{id}', [ShiftController::class, 'edit'])->name('shift.edit');
    Route::post('/shift/update/{id}', [ShiftController::class, 'update'])->name('shift.update');
    Route::get('/shift/delete/{id}', [ShiftController::class, 'delete'])->name('shift.delete');


    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SHIFT
    |--------------------------------------------------------------------------
    */

    Route::get('/employee-shift', [EmployeeShiftController::class, 'index'])->name('employee-shift.index');
    Route::get('/employee-shift/create', [EmployeeShiftController::class, 'create'])->name('employee-shift.create');
    Route::get('/employee-shift/edit/{id}', [EmployeeShiftController::class, 'edit'])->name('employee-shift.edit');
    Route::post('/employee-shift/store', [EmployeeShiftController::class, 'store'])->name('employee-shift.store');
    Route::get('/employee-shift/delete/{id}', [EmployeeShiftController::class, 'delete'])->name('employee-shift.delete');
});

Route::prefix('insentif-threshold')->group(function () {

    Route::get('/', [InsentifThresholdController::class, 'index'])
        ->name('insentif.threshold.index');

    Route::get('/create', [InsentifThresholdController::class, 'create'])
        ->name('insentif.threshold.create');

    Route::post('/store', [InsentifThresholdController::class, 'store'])
        ->name('insentif.threshold.store');

    Route::get('/edit/{id}', [InsentifThresholdController::class, 'edit'])
        ->name('insentif.threshold.edit');

    Route::post('/update/{id}', [InsentifThresholdController::class, 'update'])
        ->name('insentif.threshold.update');

    Route::get('/delete/{id}', [InsentifThresholdController::class, 'delete'])
        ->name('insentif.threshold.delete');
});

// ========================================
// PENGAJUAN CUTI ONLINE
// ========================================
Route::prefix('pengajuan-cuti')->group(function () {
    Route::get('/login', [PengajuanCutiController::class, 'login'])->name('pengajuan-cuti.login');
    Route::get('/logout', [PengajuanCutiController::class, 'logout'])->name('pengajuan-cuti.logout');
    Route::post('/verify-manual', [PengajuanCutiController::class, 'verifyManual'])->name('pengajuan-cuti.verify-manual');
    Route::get('/qr-login', [PengajuanCutiController::class, 'qrLogin'])->name('pengajuan-cuti.qr-login');
    Route::get('/form', [PengajuanCutiController::class, 'form'])->name('pengajuan-cuti.form');
    Route::post('/submit', [PengajuanCutiController::class, 'submitForm'])->name('pengajuan-cuti.submit-form');
    Route::get('/get-leave-balance', [PengajuanCutiController::class, 'getLeaveBalance'])->name('pengajuan-cuti.get-leave-balance');
    Route::get('/progress', [PengajuanCutiController::class, 'progress'])->name('pengajuan-cuti.progress');
    Route::get('/riwayat', [PengajuanCutiController::class, 'riwayat'])->name('pengajuan-cuti.riwayat');

    // Cuti Approval (Leave Management by session logged in user)
    Route::get('/approval', [LeaveApprovalController::class, 'index'])->name('pengajuan-cuti.approval');
    Route::post('/approval/approve/{id}', [LeaveApprovalController::class, 'approve'])->name('pengajuan-cuti.approval.approve');
    Route::post('/approval/reject/{id}', [LeaveApprovalController::class, 'reject'])->name('pengajuan-cuti.approval.reject');
});
// Payroll 
Route::get('/payroll/calculate', [PayrollController::class, 'calculate'])->name('payroll.calculate');
// Route::get('/payroll-process/process', [PayrollProcessController::class, 'process'])->name('payroll-process.process');

// Employee Payroll
Route::post('/employee-payroll/{npk}/show-slip', [EmployeePayrollController::class, 'showSlip'])->name('employee-payroll.show-slip');
Route::post('/employee-payroll/view', [EmployeePayrollController::class, 'verifyPassword'])->name('employee-payroll.verify-password');
Route::get('/employee-payroll', [EmployeePayrollController::class, 'index'])->name('employee-payroll.index');
Route::get('/employee-payroll/qr-login', [EmployeePayrollController::class, 'qrLogin'])->name('employee-payroll.qr-login');
Route::get('/employee-payroll/view', [EmployeePayrollController::class, 'verifyPassword'])->name('employee-payroll.verify-password');
Route::get('/employee-payroll/show/{run_id}/{npk}', [EmployeePayrollController::class, 'showSlip'])->name('employee-payroll.view-slip');
Route::get('/employee-payroll/api/period', [EmployeePayrollController::class, 'apiPeriods'])->name('employee-payroll-api.period');

// Employee Thr
Route::get('/employee-thr/show/{run_id}/{npk}', [EmployeeThrController::class, 'showSlip'])->name('employee-thr.view-slip');
Route::post('/employee-thr/{npk}/show-slip', [EmployeeThrController::class, 'showSlip'])->name('employee-thr.show-slip');
Route::post('/employee-thr/view', [EmployeeThrController::class, 'verifyPassword'])->name('employee-thr.verify-password');
Route::get('/employee-thr', [EmployeeThrController::class, 'index'])->name('employee-thr.index');
Route::get('/employee-thr/qr-login', [EmployeeThrController::class, 'qrLogin'])->name('employee-thr.qr-login');
Route::get('/employee-thr/view', [EmployeeThrController::class, 'verifyPassword'])->name('employee-thr.verify-password');
Route::get('/employee-thr/api/period', [EmployeeThrController::class, 'apiPeriods'])->name('employee-thr-api.period');

// Employee Evaluation
Route::post('/evaluation-employee/submit', [EvaluationEmployeeController::class, 'submit'])->name('evaluation-employee.submit');
Route::get('/evaluation-employee/cbt', [EvaluationEmployeeController::class, 'cbt'])->name('evaluation-employee.cbt');
Route::get('/evaluation-employee/portal', [EvaluationEmployeeController::class, 'portal'])->name('evaluation-employee.portal');
Route::get('/evaluation-employee/thankyou', [EvaluationEmployeeController::class, 'thankyou'])->name('evaluation-employee.thankyou');
