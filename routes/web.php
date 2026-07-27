<?php

use App\Events\NotificationEvent;
use App\Events\TestEvent;
use App\Http\Controllers\ActivityLogController;
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
use App\Http\Controllers\AuditRecapController;
use App\Http\Controllers\BiodataKeluarController;
use App\Http\Controllers\BpjsExceptionController;
use App\Http\Controllers\BreakMasterController;
use App\Http\Controllers\ChuFamilyController;
use App\Http\Controllers\CompensationApproveController;
use App\Http\Controllers\CompensationsController;
use App\Http\Controllers\DeptBreaktimeController;
use App\Http\Controllers\DeptInsentifRoleController;
use App\Http\Controllers\Employee6sAssignmentController;
use App\Http\Controllers\EmployeeExitHistoryController;
use App\Http\Controllers\EmployeeLateController;
use App\Http\Controllers\EmployeeMutationController;
use App\Http\Controllers\LeaveApprovalController;
use App\Http\Controllers\EmployeePayrollController;
use App\Http\Controllers\EmployeesContractController;
use App\Http\Controllers\EmployeeShiftController;
use App\Http\Controllers\EmployeeThrController;
use App\Http\Controllers\EmployeeViolationController;
use App\Http\Controllers\EpoController;
use App\Http\Controllers\EvaluationEmployeeController;
use App\Http\Controllers\EvaluationJobscopeController;
use App\Http\Controllers\EvaluationQuestionnaireController;
use App\Http\Controllers\ExpatController;
use App\Http\Controllers\ForeignGuestController;
use App\Http\Controllers\GuestMasterController;
use App\Http\Controllers\HealthTestController;
use App\Http\Controllers\HeatInsentifMasterController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InsentifApprovalController;
use App\Http\Controllers\InsentifMasterController;
use App\Http\Controllers\InsentifRoleFormulaController;
use App\Http\Controllers\InsentifThresholdController;
use App\Http\Controllers\LateCompensationController;
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
use App\Http\Controllers\NotificationsContractController;
use App\Http\Controllers\ParentDeptController;
use App\Http\Controllers\PortalRecruitmentStatusController;
use App\Http\Controllers\RecruitmentFormController;
use App\Http\Controllers\SewingViolationController;
use App\Http\Controllers\IjinMeninggalkanPekerjaanController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PayrollRecapController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\JobVacancyController;
use App\Http\Controllers\RolePayrollController;
use App\Http\Controllers\QrDeviceController;
use App\Http\Controllers\CanteenController;
use App\Http\Controllers\DoorprizeController;
use App\Http\Controllers\FoodMenuController;
use App\Http\Controllers\FoodOrderController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\MonitoringDashboardController;
use App\Http\Controllers\MonitoringRekonsiliasiController;
use App\Http\Controllers\OrderImportController;

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
Route::get('/welcome', [HomeController::class, 'welcome'])->name('welcome');
// Route::get('/template/auditsewing', [TemplateController::class, 'auditsewing'])->name('template.auditsewing');
// Route::get('/template/auditnonsewing', [TemplateController::class, 'auditnonsewing'])->name('template.auditnonsewing');

Route::group(['middleware' => 'guest'], function () {
    // Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register/guest', [RegisterController::class, 'store'])->name('register.guest');

    Route::get('/login', [LoginController::class, 'login'])->name('login.guest');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login');
    Route::get('/captcha/image', [LoginController::class, 'captchaImage'])->name('captcha.image');
    Route::post('/login/qrauth', [LoginController::class, 'qrauth'])
        ->middleware('throttle:5,1') // 5 percobaan per menit per IP
        ->name('login.qrauth');
});

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [HomeController::class, 'home'])->name('home')->middleware(['auth', 'permission']);
    Route::get('/home/get-pkwt-chart', [HomeController::class, 'getPKWTChart'])->name('home.get-pkwt-chart')->middleware(['auth', 'permission']);
    Route::get('/home/get-recap-count', [HomeController::class, 'getRecapCount'])->name('home.get-recap-count')->middleware(['auth', 'permission']);
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    //Register
    Route::get('/register/create', [RegisterController::class, 'create'])->name('register.create')->middleware(['auth', 'permission']);
    Route::post('/register', [RegisterController::class, 'storeAuth'])->name('register')->middleware(['auth', 'permission']);

    // PKWT
    Route::get('/pkwt/index', [PKWTController::class, 'index'])->name('pkwt.index')->middleware(['auth', 'permission']);


    // BIODATA
    Route::get('/biodata/index', [BiodataController::class, 'index'])->name('biodata.index')->middleware(['auth', 'permission']);
    Route::get('/biodata/gender', [BiodataController::class, 'viewGender'])->name('biodata.gender')->middleware(['auth', 'permission']);
    Route::get('/biodata/get-data', [BiodataController::class, 'getData'])->name('biodata.get-data')->middleware(['auth', 'permission']);
    Route::get('/biodata/fetch-last-npk', [BiodataController::class, 'fetchLastNpk'])->name('biodata.fetch-last-npk')->middleware(['auth', 'permission']);
    Route::post('/biodata/store', [BiodataController::class, 'store'])->name('biodata.store')->middleware(['auth', 'permission']);
    Route::get('/biodata/edit/{NPK}', [BiodataController::class, 'edit'])->name('biodata.edit')->middleware(['auth', 'permission']);
    Route::post('/biodata/update/{NPK}', [BiodataController::class, 'update'])->name('biodata.update')->middleware(['auth', 'permission']);
    Route::get('/biodata/show/{NPK}', [BiodataController::class, 'show'])->name('biodata.show')->middleware(['auth', 'permission']);
    Route::post('/biodata/update-photo/{NPK}', [BiodataController::class, 'updatePhoto'])->name('biodata.update-photo')->middleware(['auth', 'permission']);
    Route::get('/biodata/exit/{NPK}', [BiodataController::class, 'exit'])->name('biodata.exit')->middleware(['auth', 'permission']);
    Route::get('/biodata/export', [BiodataController::class, 'export'])->name('biodata.export')->middleware(['auth', 'permission']);
    Route::get('/biodata/soft-files/{npk}', [BiodataController::class, 'getSoftFiles'])->name('biodata.soft-files')->middleware(['auth', 'permission']);


    // PKWT
    Route::get('/pkwt/index', [PKWTController::class, 'index'])->name('pkwt.index')->middleware(['auth', 'permission']);

    // ========================================
    // BIODATA KELUAR
    Route::get('/biodata-keluar/index', [BiodataKeluarController::class, 'index'])->name('biodata_keluar.index')->middleware(['auth', 'permission']);
    Route::get('/biodata-keluar/get-data', [BiodataKeluarController::class, 'getData'])->name('biodata_keluar.get-data')->middleware(['auth', 'permission']);
    Route::post('/biodata-keluar/update-keterangan/{npk}', [BiodataKeluarController::class, 'updateKeterangan'])->name('biodata_keluar.update-keterangan')->middleware(['auth', 'permission']);

    // ========================================
    // Riwayat Karyawan Keluar
    Route::get('/employee-exit-history/index', [EmployeeExitHistoryController::class, 'index'])->name('employee_exit_history.index')->middleware(['auth', 'permission']);
    Route::get('/employee-exit-history/get-data', [EmployeeExitHistoryController::class, 'getData'])->name('employee_exit_history.get-data')->middleware(['auth', 'permission']);


    // ========================================
    // EMPLOYEES CONTRACT
    // ========================================
    Route::prefix('employees-contract')->group(function () {
        Route::get('/',                 [EmployeesContractController::class, 'index'])->name('employees-contract.index')->middleware(['auth', 'permission']);
        Route::get('/get-data',         [EmployeesContractController::class, 'getData'])->name('employees-contract.get-data')->middleware(['auth', 'permission']);
        Route::get('/by-npk/{npk}',     [EmployeesContractController::class, 'getByNpk'])->name('employees-contract.by-npk')->middleware(['auth', 'permission']);
        Route::post('/store',           [EmployeesContractController::class, 'store'])->name('employees-contract.store')->middleware(['auth', 'permission']);
        Route::post('/stop/{id}',       [EmployeesContractController::class, 'stop'])->name('employees-contract.stop')->middleware(['auth', 'permission']);
        Route::post('/finish/{id}',     [EmployeesContractController::class, 'finish'])->name('employees-contract.finish')->middleware(['auth', 'permission']);
        Route::post('/extend/{id}',     [EmployeesContractController::class, 'extend'])->name('employees-contract.extend')->middleware(['auth', 'permission']);
        Route::post('/split/{id}',      [EmployeesContractController::class, 'split'])->name('employees-contract.split')->middleware(['auth', 'permission']);
        Route::post('/update-salary/{id}', [EmployeesContractController::class, 'updateSalary'])->name('employees-contract.update-salary')->middleware(['auth', 'permission']);
        Route::post('/delete/{id}',     [EmployeesContractController::class, 'destroy'])->name('employees-contract.destroy')->middleware(['auth', 'permission']);
        Route::get('/bagian',           [EmployeesContractController::class, 'getBagian'])->name('employees-contract.bagian')->middleware(['auth', 'permission']);
        Route::get('/template',         [EmployeesContractController::class, 'template'])->name('employees-contract.template')->middleware(['auth', 'permission']);
        Route::post('/import',          [EmployeesContractController::class, 'import'])->name('employees-contract.import')->middleware(['auth', 'permission']);
        Route::get('/export',           [EmployeesContractController::class, 'export'])->name('employees-contract.export')->middleware(['auth', 'permission']);
        Route::get('/export-all',       [EmployeesContractController::class, 'exportAll'])->name('employees-contract.export-all');
    });


    // Pelamar
    Route::get('/pelamar/index', [PelamarController::class, 'index'])->name('pelamar.index')->middleware(['auth', 'permission']);
    Route::post('/pelamar/import', [PelamarController::class, 'import'])->name('pelamar.import')->middleware(['auth', 'permission']);
    Route::post('/pelamar/assign', [PelamarController::class, 'assign'])->name('pelamar.assign')->middleware(['auth', 'permission']);
    Route::get('/pelamar/detail/{id}', [PelamarController::class, 'detail'])->name('pelamar.detail')->middleware(['auth', 'permission']);
    Route::get('/pelamar/template', [PelamarController::class, 'exportTemplate'])->name('pelamar.template')->middleware(['auth', 'permission']);

    // Recruitment Position
    Route::prefix('recruitment-position')->middleware(['auth', 'permission'])->group(function () {
        Route::get('/', [\App\Http\Controllers\RecruitmentPositionController::class, 'index'])->name('recruitment-position.index');
        Route::get('/get-data', [\App\Http\Controllers\RecruitmentPositionController::class, 'getData'])->name('recruitment-position.get-data');
        Route::post('/store', [\App\Http\Controllers\RecruitmentPositionController::class, 'store'])->name('recruitment-position.store');
        Route::put('/update/{id}', [\App\Http\Controllers\RecruitmentPositionController::class, 'update'])->name('recruitment-position.update');
        Route::delete('/destroy/{id}', [\App\Http\Controllers\RecruitmentPositionController::class, 'destroy'])->name('recruitment-position.destroy');
    });

    // Activity Log
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/data', [ActivityLogController::class, 'data'])->name('activity-logs.data');
    });


    //Role
    Route::get('/role/index', [RoleController::class, 'index'])->name('role.index')->middleware(['auth', 'permission']);
    Route::get('/role/delete/{id}', [RoleController::class, 'delete'])->name('role.delete')->middleware(['auth', 'permission']);
    Route::get('/role/create', [RoleController::class, 'create'])->name('role.create')->middleware(['auth', 'permission']);
    Route::post('/role/store', [RoleController::class, 'store'])->name('role.store')->middleware(['auth', 'permission']);
    Route::get('/role/find/{id}', [RoleController::class, 'find'])->name('role.find')->middleware(['auth', 'permission']);
    Route::post('/role/update', [RoleController::class, 'update'])->name('role.update')->middleware(['auth', 'permission']);

    //User
    Route::get('/user/index', [UserController::class, 'index'])->name('user.index')->middleware(['auth', 'permission']);
    Route::get('/user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::post('/user/update', [UserController::class, 'update'])->name('user.update')->middleware(['auth', 'permission']);
    Route::get('/user/detail/{id}', [UserController::class, 'detail'])->name('user.detail')->middleware(['auth', 'permission']);
    Route::get('/user/delete/{id}', [UserController::class, 'delete'])->name('user.delete')->middleware(['auth', 'permission']);
    Route::get('/user/assign/{id}', [UserController::class, 'assign'])->name('user.assign')->middleware(['auth', 'permission']);
    Route::post('/user/assignrole', [UserController::class, 'assignrole'])->name('user.assignrole')->middleware(['auth', 'permission']);

    // Payroll Component
    Route::get('/payroll-components/index', [PayrollComponentController::class, 'index'])->name('payroll-components.index')->middleware(['auth', 'permission']);
    Route::get('/payroll-components/create', [PayrollComponentController::class, 'create'])->name('payroll-components.create')->middleware(['auth', 'permission']);
    Route::post('/payroll-components/store', [PayrollComponentController::class, 'store'])->name('payroll-components.store')->middleware(['auth', 'permission']);
    Route::get('/payroll-components/detail/{id}', [PayrollComponentController::class, 'detail'])->name('payroll-components.detail')->middleware(['auth', 'permission']);
    Route::get('/payroll-components/edit/{id}', [PayrollComponentController::class, 'edit'])->name('payroll-components.edit')->middleware(['auth', 'permission']);
    Route::post('/payroll-components/update', [PayrollComponentController::class, 'update'])->name('payroll-components.update')->middleware(['auth', 'permission']);
    Route::get('/payroll-components/delete/{id}', [PayrollComponentController::class, 'delete'])->name('payroll-components.delete')->middleware(['auth', 'permission']);

    // Payroll Period
    Route::get('/payroll-periods/index', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index')->middleware(['auth', 'permission']);
    Route::get('/payroll-periods/create', [PayrollPeriodController::class, 'create'])->name('payroll-periods.create')->middleware(['auth', 'permission']);
    Route::post('/payroll-periods/store', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store')->middleware(['auth', 'permission']);
    Route::get('/payroll-periods/detail/{id}', [PayrollPeriodController::class, 'detail'])->name('payroll-periods.detail')->middleware(['auth', 'permission']);
    Route::get('/payroll-periods/edit/{id}', [PayrollPeriodController::class, 'edit'])->name('payroll-periods.edit')->middleware(['auth', 'permission']);
    Route::post('/payroll-periods/update', [PayrollPeriodController::class, 'update'])->name('payroll-periods.update')->middleware(['auth', 'permission']);
    Route::get('/payroll-periods/delete/{id}', [PayrollPeriodController::class, 'delete'])->name('payroll-periods.delete')->middleware(['auth', 'permission']);

    // Payroll Process
    Route::get('/payroll-process/index', [PayrollProcessController::class, 'index'])->name('payroll-process.index')->middleware(['auth', 'permission']);
    Route::get('/payroll-process/generate', [PayrollProcessController::class, 'generate'])->name('payroll-process.generate')->middleware(['auth', 'permission']);
    Route::get('/payroll-process/check/{period_id}', [PayrollProcessController::class, 'checkPayroll'])->name('payroll-process.check')->middleware(['auth', 'permission']);
    Route::post('/payroll-process/process', [PayrollProcessController::class, 'process'])->name('payroll-process.process')->middleware(['auth', 'permission']);
    Route::get('/payroll-process/details/{id}', [PayrollProcessController::class, 'details'])->name('payroll-process.details')->middleware(['auth', 'permission']);
    Route::delete('/payroll-process/delete/{period_id}', [PayrollProcessController::class, 'destroy'])->name('payroll-process.destroy')->middleware(['auth', 'permission']);
    Route::get('/payroll-process/edit/{id}', [PayrollProcessController::class, 'edit'])->name('payroll-process.edit')->middleware(['auth', 'permission']);
    Route::post('/payroll-process/update', [PayrollProcessController::class, 'update'])->name('payroll-process.update')->middleware(['auth', 'permission']);
    Route::get('/payroll-slip/{run_id}/{npk}', [PayrollProcessController::class, 'slip'])->name('payroll-slip')->middleware(['auth', 'permission']);
    Route::get('payroll-process/export-rekap/{run_id}', [PayrollProcessController::class, 'exportRekap'])->name('payroll.export.rekap')->middleware(['auth', 'permission']);
    Route::get('/payroll/export/{run_id}', [PayrollProcessController::class, 'export'])->name('payroll.export.export')->middleware(['auth', 'permission']);
    Route::get('/payroll/export-progress/{run_id}', [PayrollProcessController::class, 'progress'])->name('payroll.export.progress')->middleware(['auth', 'permission']);
    Route::get('/payroll/process-progress/{period_id}', [PayrollProcessController::class, 'progressRun'])->name('payroll.process.progress')->middleware(['auth', 'permission']);
    Route::get('/payroll-slip/view/{run_id}/{npk}', [PayrollProcessController::class, 'passwordForm'])->name('payroll-slip.view')->middleware(['auth', 'permission']);
    Route::get('/payroll-process/approval/{period}', [PayrollProcessController::class, 'approvalStatus'])->name('payroll-process.approval')->middleware(['auth', 'permission']);
    Route::post('/payroll-process/update-pph21', [PayrollProcessController::class, 'updatePph21'])->name('payroll-process.update-pph21')->middleware(['auth', 'permission']);
    Route::post('/payroll-process/update-pph-by-contract/{run_id}', [PayrollProcessController::class, 'updatePphByContract'])->name('payroll-process.update-pph-by-contract')->middleware(['auth', 'permission']);
    Route::post('/payroll-process/recreate-document/{run_id}', [PayrollProcessController::class, 'recreateDocument'])->name('payroll-process.recreate-document')->middleware(['auth', 'permission']);

    //Payroll Master
    Route::get('/payroll-master', [PayrollMasterController::class, 'index'])->name('payroll-master.index')->middleware(['auth', 'permission']);
    Route::get('/payroll-master/create', [PayrollMasterController::class, 'create'])->name('payroll-master.create')->middleware(['auth', 'permission']);
    Route::get('/payroll-master/edit/{id}', [PayrollMasterController::class, 'edit'])->name('payroll-master.edit')->middleware(['auth', 'permission']);
    Route::post('/payroll-master/update/{id}', [PayrollMasterController::class, 'update'])->name('payroll-master.update')->middleware(['auth', 'permission']);
    Route::get('/payroll-master/delete/{id}', [PayrollMasterController::class, 'delete'])->name('payroll-master.delete')->middleware(['auth', 'permission']);
    Route::post('/payroll-master/store', [PayrollMasterController::class, 'store'])->name('payroll-master.store')->middleware(['auth', 'permission']);
    Route::post('/payroll-master/import', [PayrollMasterController::class, 'import'])->name('payroll-master.import')->middleware(['auth', 'permission']);
    Route::get('/payroll-master/template', [PayrollMasterController::class, 'template'])->name('payroll-master.template')->middleware(['auth', 'permission']);


    // Thr Period
    Route::get('/thr-periods/index', [ThrPeriodController::class, 'index'])->name('thr-periods.index')->middleware(['auth', 'permission']);
    Route::get('/thr-periods/create', [ThrPeriodController::class, 'create'])->name('thr-periods.create')->middleware(['auth', 'permission']);
    Route::post('/thr-periods/store', [ThrPeriodController::class, 'store'])->name('thr-periods.store')->middleware(['auth', 'permission']);
    Route::get('/thr-periods/detail/{id}', [ThrPeriodController::class, 'detail'])->name('thr-periods.detail')->middleware(['auth', 'permission']);
    Route::get('/thr-periods/edit/{id}', [ThrPeriodController::class, 'edit'])->name('thr-periods.edit')->middleware(['auth', 'permission']);
    Route::post('/thr-periods/update', [ThrPeriodController::class, 'update'])->name('thr-periods.update')->middleware(['auth', 'permission']);
    Route::get('/thr-periods/delete/{id}', [ThrPeriodController::class, 'delete'])->name('thr-periods.delete')->middleware(['auth', 'permission']);

    // Thr Process
    Route::get('/thr-process/index', [ThrProcessController::class, 'index'])->name('thr-process.index')->middleware(['auth', 'permission']);
    Route::get('/thr-process/generate', [ThrProcessController::class, 'generate'])->name('thr-process.generate')->middleware(['auth', 'permission']);
    Route::post('/thr-process/process', [ThrProcessController::class, 'process'])->name('thr-process.process')->middleware(['auth', 'permission']);
    Route::get('/thr-process/details/{id}', [ThrProcessController::class, 'details'])->name('thr-process.details')->middleware(['auth', 'permission']);
    Route::delete('/thr-process/delete/{period_id}', [ThrProcessController::class, 'destroy'])->name('thr-process.destroy')->middleware(['auth', 'permission']);
    Route::get('/thr-process/edit/{id}', [ThrProcessController::class, 'edit'])->name('thr-process.edit')->middleware(['auth', 'permission']);
    Route::post('/thr-process/update', [ThrProcessController::class, 'update'])->name('thr-process.update')->middleware(['auth', 'permission']);
    Route::get('/thr-slip/{run_id}/{npk}', [ThrProcessController::class, 'slip'])->name('thr-slip')->middleware(['auth', 'permission']);
    Route::get('thr-process/export-rekap/{run_id}', [ThrProcessController::class, 'exportRekap'])->name('thr.export.rekap')->middleware(['auth', 'permission']);
    Route::get('/thr/export/{run_id}', [ThrProcessController::class, 'export'])->name('thr.export.export')->middleware(['auth', 'permission']);
    Route::get('/thr/export-progress/{id}', [ThrProcessController::class, 'progress'])->name('thr.export.progress');
    Route::get('/thr/process-progress/{period_id}', [ThrProcessController::class, 'progressRun'])->name('thr.process.progress');
    Route::get('/thr-slip/view/{run_id}/{npk}', [ThrProcessController::class, 'passwordForm'])->name('thr-slip.view')->middleware(['auth', 'permission']);
    Route::get('/thr-process/approval/{period}', [ThrProcessController::class, 'approvalStatus'])->name('thr-process.approval')->middleware(['auth', 'permission']);
    Route::post('/thr-process/check', [ThrProcessController::class, 'check'])->name('thr-process.check')->middleware(['auth', 'permission']);
    // Route::get('/thr-process/check/{period_id}', [ThrProcessController::class, 'check'])->name('thr-process.check')->middleware(['auth', 'permission']);

    // Evaluation Questionnaire
    Route::prefix('evaluation-questionnaire')->group(function () {
        Route::get('/', [EvaluationQuestionnaireController::class, 'index'])->name('evaluation-questionnaire.index')->middleware(['auth', 'permission']);
        Route::post('/import', [EvaluationQuestionnaireController::class, 'import'])->name('evaluation-questionnaire.import')->middleware(['auth', 'permission']);
        Route::get('/template', [EvaluationQuestionnaireController::class, 'template'])->name('evaluation-questionnaire.template')->middleware(['auth', 'permission']);
        Route::get('/delete/{id}', [EvaluationQuestionnaireController::class, 'delete'])->name('evaluation-questionnaire.delete')->middleware(['auth', 'permission']);
    });

    // ========================================
    // NOTIFICATIONS CONTRACT
    // ========================================
    Route::prefix('api/notifications')->name('api.notifications.')->group(function () {
        Route::get('/', [NotificationsContractController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationsContractController::class, 'unread'])->name('unread');
        Route::get('/statistics', [NotificationsContractController::class, 'statistics'])->name('statistics');
        Route::get('/{id}', [NotificationsContractController::class, 'show'])->name('show');
        Route::post('/{id}/read', [NotificationsContractController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationsContractController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{id}/archive', [NotificationsContractController::class, 'archive'])->name('archive');
        Route::delete('/{id}', [NotificationsContractController::class, 'destroy'])->name('destroy');
    });

    // Payroll Approve
    Route::prefix('payroll-approve')->group(function () {
        Route::get('/', [PayrollApproveController::class, 'index'])->name('payroll-approve.index')->middleware(['auth', 'permission']);
        Route::post('/create/{payroll_run_id}', [PayrollApproveController::class, 'store'])->name('payroll-approve.create')->middleware(['auth', 'permission']);
        Route::post('/{id}/approve', [PayrollApproveController::class, 'approve'])->name('payroll-approve.approve')->middleware(['auth', 'permission']);
    });

    // Compensation Approve
    Route::prefix('compensation-approve')->group(function () {
        Route::get('/', [CompensationApproveController::class, 'index'])->name('compensation-approve.index')->middleware(['auth', 'permission']);
        Route::post('/create/{run_id}', [CompensationApproveController::class, 'store'])->name('compensation-approve.create')->middleware(['auth', 'permission']);
        Route::post('/{id}/approve', [CompensationApproveController::class, 'approve'])->name('compensation-approve.approve')->middleware(['auth', 'permission']);
    });



    // Thr Approve
    Route::post('/thr-approve/{id}/approve', [ThrApproveController::class, 'approve'])->name('thr-approve.approve')->middleware(['auth', 'permission']);
    Route::prefix('thr-approve')->group(function () {
        Route::get('/', [ThrApproveController::class, 'index'])->name('thr-approve.index')->middleware(['auth', 'permission']);
        Route::post('/create/{thr_run_id}', [ThrApproveController::class, 'store'])->name('thr-approve.create')->middleware(['auth', 'permission']);
        Route::post('/{id}/approve', [ThrApproveController::class, 'approve'])->name('thr-approve.approve')->middleware(['auth', 'permission']);
    });


    // Insentif Approve
    Route::get('/insentif-approve', [InsentifApprovalController::class, 'index'])->name('insentif-approve.index')->middleware(['auth', 'permission']);
    Route::post('/insentif-approve/{id}/approve', [InsentifApprovalController::class, 'approve'])->name('insentif-approve.approve')->middleware(['auth', 'permission']);
    Route::get('/insentif-approve/{id}/detail', [InsentifApprovalController::class, 'detail'])->name('insentif-approve.detail')->middleware(['auth', 'permission']);

    Route::prefix('payroll-setting')->group(function () {
        Route::get('/', [PayrollSettingController::class, 'index'])->name('payroll-setting.index')->middleware(['auth', 'permission']);
        Route::post('/store', [PayrollSettingController::class, 'store'])->name('payroll-setting.store')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [PayrollSettingController::class, 'edit'])->name('payroll-setting.edit')->middleware(['auth', 'permission']);
        Route::put('/update/{id}', [PayrollSettingController::class, 'update'])->name('payroll-setting.update')->middleware(['auth', 'permission']);
        Route::delete('/delete/{id}', [PayrollSettingController::class, 'delete'])->name('payroll-setting.delete')->middleware(['auth', 'permission']);
    });

    // Evaluation Jobscope
    Route::prefix('evaluation-jobscope')->group(function () {
        Route::get('/', [EvaluationJobscopeController::class, 'index'])->name('evaluation-jobscope.index')->middleware(['auth', 'permission']);
        Route::get('/delete/{id}', [EvaluationJobscopeController::class, 'delete'])->name('evaluation-jobscope.delete')->middleware(['auth', 'permission']);
    });

    Route::get('/ijin-meninggalkan-pekerjaan', [IjinMeninggalkanPekerjaanController::class, 'index'])->name('ijin-meninggalkan-pekerjaan.index')->middleware(['auth', 'permission']);
    Route::get('/ijin-meninggalkan-pekerjaan/create', [IjinMeninggalkanPekerjaanController::class, 'create'])->name('ijin-meninggalkan-pekerjaan.create')->middleware(['auth', 'permission']);
    Route::post('/ijin-meninggalkan-pekerjaan', [IjinMeninggalkanPekerjaanController::class, 'store'])->name('ijin-meninggalkan-pekerjaan.store')->middleware(['auth', 'permission']);
    Route::get('/ijin-meninggalkan-pekerjaan/{id}/edit', [IjinMeninggalkanPekerjaanController::class, 'edit'])->name('ijin-meninggalkan-pekerjaan.edit')->middleware(['auth', 'permission']);
    Route::put('/ijin-meninggalkan-pekerjaan/{id}', [IjinMeninggalkanPekerjaanController::class, 'update'])->name('ijin-meninggalkan-pekerjaan.update')->middleware(['auth', 'permission']);
    Route::get('/ijin-meninggalkan-pekerjaan/{id}', [IjinMeninggalkanPekerjaanController::class, 'destroy'])->name('ijin-meninggalkan-pekerjaan.delete')->middleware(['auth', 'permission']);

    //  Evaluation Employee
    Route::get('/evaluation-employee', [EvaluationEmployeeController::class, 'index'])->name('evaluation-employee.index')->middleware(['auth', 'permission']);

    // Holiday
    Route::get('holidays/sync', [HolidayController::class, 'sync'])->name('holidays.sync')->middleware(['auth', 'permission']);
    Route::get('holidays/index', [HolidayController::class, 'index'])->name('holidays.index')->middleware(['auth', 'permission']);
    Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create')->middleware(['auth', 'permission']);
    Route::post('holidays/store', [HolidayController::class, 'store'])->name('holidays.store')->middleware(['auth', 'permission']);
    Route::get('holidays/delete/{id}', [HolidayController::class, 'destroy'])->name('holidays.delete')->middleware(['auth', 'permission']);
    Route::get('/holidays/edit/{id}', [HolidayController::class, 'edit'])->name('holidays.edit')->middleware(['auth', 'permission']);
    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import')->middleware(['auth', 'permission']);
    Route::get('holidays/export', [HolidayController::class, 'export'])->name('holidays.export')->middleware(['auth', 'permission']);

    // Line Insentif Master
    Route::get('line-insentif-master/index', [LineInsentifMasterController::class, 'index'])->name('line-insentif-master.index')->middleware(['auth', 'permission']);
    Route::get('line-insentif-master/create', [LineInsentifMasterController::class, 'create'])->name('line-insentif-master.create')->middleware(['auth', 'permission']);
    Route::post('line-insentif-master/store', [LineInsentifMasterController::class, 'store'])->name('line-insentif-master.store')->middleware(['auth', 'permission']);
    Route::get('line-insentif-master/delete/{id}', [LineInsentifMasterController::class, 'destroy'])->name('line-insentif-master.delete')->middleware(['auth', 'permission']);
    Route::get('/line-insentif-master/edit/{id}', [LineInsentifMasterController::class, 'edit'])->name('line-insentif-master.edit')->middleware(['auth', 'permission']);
    Route::post('line-insentif-master/import', [LineInsentifMasterController::class, 'import'])->name('line-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('line-insentif-master/export', [LineInsentifMasterController::class, 'export'])->name('line-insentif-master.export')->middleware(['auth', 'permission']);
    Route::get('/line-insentif-master/template', [LineInsentifMasterController::class, 'template'])->name('line-insentif-master.template')->middleware(['auth', 'permission']);
    Route::post('/line-insentif-master/import', [LineInsentifMasterController::class, 'import'])->name('line-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('/line-insentif-master/{period}/check', [LineInsentifMasterController::class, 'check'])->name('line-insentif-master.check')->middleware(['auth', 'permission']);
    Route::get('/line-insentif-master/{period}/data', [LineInsentifMasterController::class, 'getData'])->name('line-insentif-master.data')->middleware(['auth', 'permission']);

    // Cutting Insentif Master
    Route::get('cutting-insentif-master/index', [CuttingInsentifMasterController::class, 'index'])->name('cutting-insentif-master.index')->middleware(['auth', 'permission']);
    Route::get('cutting-insentif-master/create', [CuttingInsentifMasterController::class, 'create'])->name('cutting-insentif-master.create')->middleware(['auth', 'permission']);
    Route::post('cutting-insentif-master/store', [CuttingInsentifMasterController::class, 'store'])->name('cutting-insentif-master.store')->middleware(['auth', 'permission']);
    Route::get('cutting-insentif-master/delete/{id}', [CuttingInsentifMasterController::class, 'destroy'])->name('cutting-insentif-master.delete')->middleware(['auth', 'permission']);
    Route::get('/cutting-insentif-master/edit/{id}', [CuttingInsentifMasterController::class, 'edit'])->name('cutting-insentif-master.edit')->middleware(['auth', 'permission']);
    Route::post('cutting-insentif-master/import', [CuttingInsentifMasterController::class, 'import'])->name('cutting-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('cutting-insentif-master/export', [CuttingInsentifMasterController::class, 'export'])->name('cutting-insentif-master.export')->middleware(['auth', 'permission']);
    Route::get('/cutting-insentif-master/template', [CuttingInsentifMasterController::class, 'template'])->name('cutting-insentif-master.template')->middleware(['auth', 'permission']);
    Route::post('/cutting-insentif-master/import', [CuttingInsentifMasterController::class, 'import'])->name('cutting-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('/cutting-insentif-master/{period}/check', [CuttingInsentifMasterController::class, 'check'])->name('cutting-insentif-master.check')->middleware(['auth', 'permission']);
    Route::get('/cutting-insentif-master/{period}/data', [CuttingInsentifMasterController::class, 'getData'])->name('cutting-insentif-master.data')->middleware(['auth', 'permission']);

    // Pad Print Insentif Master
    Route::get('pad-insentif-master/index', [PadInsentifMasterController::class, 'index'])->name('pad-insentif-master.index')->middleware(['auth', 'permission']);
    Route::get('pad-insentif-master/create', [PadInsentifMasterController::class, 'create'])->name('pad-insentif-master.create')->middleware(['auth', 'permission']);
    Route::post('pad-insentif-master/store', [PadInsentifMasterController::class, 'store'])->name('pad-insentif-master.store')->middleware(['auth', 'permission']);
    Route::get('pad-insentif-master/delete/{id}', [PadInsentifMasterController::class, 'destroy'])->name('pad-insentif-master.delete')->middleware(['auth', 'permission']);
    Route::get('/pad-insentif-master/edit/{id}', [PadInsentifMasterController::class, 'edit'])->name('pad-insentif-master.edit')->middleware(['auth', 'permission']);
    Route::post('pad-insentif-master/import', [PadInsentifMasterController::class, 'import'])->name('pad-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('pad-insentif-master/export', [PadInsentifMasterController::class, 'export'])->name('pad-insentif-master.export')->middleware(['auth', 'permission']);
    Route::get('/pad-insentif-master/template', [PadInsentifMasterController::class, 'template'])->name('pad-insentif-master.template')->middleware(['auth', 'permission']);
    Route::post('/pad-insentif-master/import', [PadInsentifMasterController::class, 'import'])->name('pad-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('/pad-insentif-master/{period}/check', [PadInsentifMasterController::class, 'check'])->name('pad-insentif-master.check')->middleware(['auth', 'permission']);
    Route::get('/pad-insentif-master/{period}/data', [PadInsentifMasterController::class, 'getData'])->name('pad-insentif-master.data')->middleware(['auth', 'permission']);


    // Heat Insentif Master
    Route::get('heat-insentif-master/index', [HeatInsentifMasterController::class, 'index'])->name('heat-insentif-master.index')->middleware(['auth', 'permission']);
    Route::get('heat-insentif-master/create', [HeatInsentifMasterController::class, 'create'])->name('heat-insentif-master.create')->middleware(['auth', 'permission']);
    Route::post('heat-insentif-master/store', [HeatInsentifMasterController::class, 'store'])->name('heat-insentif-master.store')->middleware(['auth', 'permission']);
    Route::get('heat-insentif-master/delete/{id}', [HeatInsentifMasterController::class, 'destroy'])->name('heat-insentif-master.delete')->middleware(['auth', 'permission']);
    Route::get('/heat-insentif-master/edit/{id}', [HeatInsentifMasterController::class, 'edit'])->name('heat-insentif-master.edit')->middleware(['auth', 'permission']);
    Route::post('heat-insentif-master/import', [HeatInsentifMasterController::class, 'import'])->name('heat-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('heat-insentif-master/export', [HeatInsentifMasterController::class, 'export'])->name('heat-insentif-master.export')->middleware(['auth', 'permission']);
    Route::get('/heat-insentif-master/template', [HeatInsentifMasterController::class, 'template'])->name('heat-insentif-master.template')->middleware(['auth', 'permission']);
    Route::post('/heat-insentif-master/import', [HeatInsentifMasterController::class, 'import'])->name('heat-insentif-master.import')->middleware(['auth', 'permission']);
    Route::get('/heat-insentif-master/{period}/check', [HeatInsentifMasterController::class, 'check'])->name('heat-insentif-master.check')->middleware(['auth', 'permission']);
    Route::get('/heat-insentif-master/{period}/data', [HeatInsentifMasterController::class, 'getData'])->name('heat-insentif-master.data')->middleware(['auth', 'permission']);

    // Dept Insentif Role
    Route::get('dept-insentif-role', [DeptInsentifRoleController::class, 'index'])->name('dept-insentif-role.index')->middleware(['auth', 'permission']);
    Route::get('dept-insentif-role/create', [DeptInsentifRoleController::class, 'create'])->name('dept-insentif-role.create')->middleware(['auth', 'permission']);
    Route::post('dept-insentif-role/store', [DeptInsentifRoleController::class, 'store'])->name('dept-insentif-role.store')->middleware(['auth', 'permission']);
    Route::get('dept-insentif-role/edit/{id}', [DeptInsentifRoleController::class, 'edit'])->name('dept-insentif-role.edit')->middleware(['auth', 'permission']);
    Route::post('dept-insentif-role/update/{id}', [DeptInsentifRoleController::class, 'update'])->name('dept-insentif-role.update')->middleware(['auth', 'permission']);
    Route::get('dept-insentif-role/delete/{id}', [DeptInsentifRoleController::class, 'delete'])->name('dept-insentif-role.delete')->middleware(['auth', 'permission']);

    // Insentif Role Formula
    Route::get('/insentif-role-formulas', [InsentifRoleFormulaController::class, 'index'])->name('insentif-role-formulas.index')->middleware(['auth', 'permission']);
    Route::get('/insentif-role-formulas/create', [InsentifRoleFormulaController::class, 'create'])->name('insentif-role-formulas.create')->middleware(['auth', 'permission']);
    Route::post('/insentif-role-formulas/store', [InsentifRoleFormulaController::class, 'store'])->name('insentif-role-formulas.store')->middleware(['auth', 'permission']);
    Route::get('/insentif-role-formulas/edit/{id}', [InsentifRoleFormulaController::class, 'edit'])->name('insentif-role-formulas.edit')->middleware(['auth', 'permission']);
    Route::put('/insentif-role-formulas/update/{id}', [InsentifRoleFormulaController::class, 'update'])->name('insentif-role-formulas.update')->middleware(['auth', 'permission']);
    Route::get('/insentif-role-formulas/delete/{id}', [InsentifRoleFormulaController::class, 'delete'])->name('insentif-role-formulas.delete')->middleware(['auth', 'permission']);

    Route::prefix('payroll-adjusments')->group(function () {
        Route::get('/', [PayrollAdjusmentController::class, 'index'])->name('payroll-adjusments.index')->middleware(['auth', 'permission']);
        Route::get('/create', [PayrollAdjusmentController::class, 'create'])->name('payroll-adjusments.create')->middleware(['auth', 'permission']);
        Route::post('/store', [PayrollAdjusmentController::class, 'store'])->name('payroll-adjusments.store')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [PayrollAdjusmentController::class, 'edit'])->name('payroll-adjusments.edit')->middleware(['auth', 'permission']);
        Route::post('/update/{id}', [PayrollAdjusmentController::class, 'update'])->name('payroll-adjusments.update')->middleware(['auth', 'permission']);
        Route::delete('/delete/{id}', [PayrollAdjusmentController::class, 'destroy'])->name('payroll-adjusments.destroy')->middleware(['auth', 'permission']);
    });

    Route::prefix('rbac')->middleware(['auth', 'permission'])->group(function () {

        // CRUD Permission
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permission.index');
        Route::get('/permissions/create', [PermissionController::class, 'create'])->name('permission.create');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permission.store');
        Route::get('/permissions/{id}/edit', [PermissionController::class, 'edit'])->name('permission.edit');
        Route::put('/permissions/{id}', [PermissionController::class, 'update'])->name('permission.update');
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy'])->name('permission.destroy');

        // CRUD Menu
        Route::get('/menus', [MenuController::class, 'index'])->name('menu.index');
        Route::get('/menus/create', [MenuController::class, 'create'])->name('menu.create');
        Route::post('/menus', [MenuController::class, 'store'])->name('menu.store');
        Route::get('/menus/{id}/edit', [MenuController::class, 'edit'])->name('menu.edit');
        Route::put('/menus/{id}', [MenuController::class, 'update'])->name('menu.update');
        Route::delete('/menus/{id}', [MenuController::class, 'destroy'])->name('menu.destroy');
        Route::post('/menus/reorder', [MenuController::class, 'reorder'])->name('menu.reorder');

        // Assign Permission ke Role
        Route::get('/role-permissions', [RolePermissionController::class, 'index'])->name('role-permission.index');
        Route::get('/role-permissions/{role}/edit', [RolePermissionController::class, 'edit'])->name('role-permission.edit');
        Route::put('/role-permissions/{role}', [RolePermissionController::class, 'update'])->name('role-permission.update');
    });

    // attendance finger
    Route::get('/attendance-finger/index', [AttendanceFingerController::class, 'index'])->name('attendance-finger.index')->middleware(['auth', 'permission']);
    Route::post('/attendance-finger/sync', [AttendanceFingerController::class, 'sync'])->name('attendance-finger.sync')->middleware(['auth', 'permission']);
    Route::post('/attendance-finger/export', [AttendanceFingerController::class, 'export'])->name('attendance-finger.export')->middleware(['auth', 'permission']);
    Route::get('/attendance-finger/not-finger', [AttendanceFingerController::class, 'notFinger'])->name('attendance-finger.not-finger')->middleware(['auth', 'permission']);
    Route::post('/attendance-finger/export-not-finger', [AttendanceFingerController::class, 'exportNotFinger'])->name('attendance-finger.export-not-finger')->middleware(['auth', 'permission']);
    Route::post('/attendance-finger/assign-attendance', [AttendanceFingerController::class, 'assignAttendance'])->name('attendance-finger.assign-attendance')->middleware(['auth', 'permission']);
    Route::post('/attendance-finger/download-template-manual', [AttendanceFingerController::class, 'downloadTemplateManual'])->name('attendance-finger.download-template-manual')->middleware(['auth', 'permission']);

    /*
|--------------------------------------------------------------------------
| Break Master
|--------------------------------------------------------------------------
*/

    Route::get('/break-master', [BreakMasterController::class, 'index'])->name('break-master.index')->middleware(['auth', 'permission']);
    Route::get('/break-master/create', [BreakMasterController::class, 'create'])->name('break-master.create')->middleware(['auth', 'permission']);
    Route::post('/break-master/store', [BreakMasterController::class, 'store'])->name('break-master.store')->middleware(['auth', 'permission']);
    Route::get('/break-master/{id}/edit', [BreakMasterController::class, 'edit'])->name('break-master.edit')->middleware(['auth', 'permission']);
    Route::put('/break-master/{id}', [BreakMasterController::class, 'update'])->name('break-master.update')->middleware(['auth', 'permission']);
    Route::delete('/break-master/{id}', [BreakMasterController::class, 'destroy'])->name('break-master.destroy')->middleware(['auth', 'permission']);

    /*
|--------------------------------------------------------------------------
| Department Breaktime
|--------------------------------------------------------------------------
*/

    Route::get('/dept-breaktime', [DeptBreaktimeController::class, 'index'])->name('dept-breaktime.index')->middleware(['auth', 'permission']);
    Route::get('/dept-breaktime/create', [DeptBreaktimeController::class, 'create'])->name('dept-breaktime.create')->middleware(['auth', 'permission']);
    Route::post('/dept-breaktime/store', [DeptBreaktimeController::class, 'store'])->name('dept-breaktime.store')->middleware(['auth', 'permission']);
    Route::get('/dept-breaktime/{id}/edit', [DeptBreaktimeController::class, 'edit'])->name('dept-breaktime.edit')->middleware(['auth', 'permission']);
    Route::put('/dept-breaktime/{id}', [DeptBreaktimeController::class, 'update'])->name('dept-breaktime.update')->middleware(['auth', 'permission']);
    Route::delete('/dept-breaktime/{id}', [DeptBreaktimeController::class, 'destroy'])->name('dept-breaktime.destroy')->middleware(['auth', 'permission']);

    //Attendance
    Route::get('/attendance/index', [AttendanceController::class, 'index'])->name('attendance.index')->middleware(['auth', 'permission']);
    Route::post('/attendance/import', [AttendanceController::class, 'import'])->name('attendance.import')->middleware(['auth', 'permission']);
    Route::post('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export')->middleware(['auth', 'permission']);
    Route::get('/attendance/export_view', [AttendanceController::class, 'export_view'])->name('attendance.export_view')->middleware(['auth', 'permission']);
    Route::post('/attendance/deleteAll', [AttendanceController::class, 'deleteAll'])->name('attendance.deleteAll')->middleware(['auth', 'permission']);
    Route::post('/attendance/auditsewing', [AttendanceController::class, 'auditsewing'])->name('attendance.auditsewing')->middleware(['auth', 'permission']);
    Route::post('/attendance/auditnonsewing', [AttendanceController::class, 'auditnonsewing'])->name('attendance.auditnonsewing')->middleware(['auth', 'permission']);
    Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report')->middleware(['auth', 'permission']);
    Route::get('/attendance/check-master-data', [AttendanceController::class, 'checkMasterData'])->name('attendance.checkMasterData')->middleware(['auth', 'permission']);
    Route::get('/attendance/edit/{id}', [AttendanceController::class, 'edit'])->name('attendance.edit')->middleware(['auth', 'permission']);
    Route::post('/attendance/update/{id}', [AttendanceController::class, 'update'])->name('attendance.update')->middleware(['auth', 'permission']);
    Route::get('/attendance/showAttendance', [AttendanceController::class, 'showAttendance'])->name('attendance.showAttendance')->middleware(['auth', 'permission']);

    // PARENT DEPT
    Route::get('/parent-dept/index', [ParentDeptController::class, 'index'])->name('parent-dept.index')->middleware(['auth', 'permission']);
    Route::get('/parent-dept/template', [ParentDeptController::class, 'exportTemplate'])->name('parent-dept.template')->middleware(['auth', 'permission']);
    Route::get('/parent-dept/export', [ParentDeptController::class, 'exportData'])->name('parent-dept.export')->middleware(['auth', 'permission']);
    Route::post('/parent-dept/import', [ParentDeptController::class, 'import'])->name('parent-dept.import')->middleware(['auth', 'permission']);
    Route::get('/parent-dept/get-data', [ParentDeptController::class, 'getData'])->name('parent-dept.get-data')->middleware(['auth', 'permission']);
    Route::post('/parent-dept/store', [ParentDeptController::class, 'store'])->name('parent-dept.store')->middleware(['auth', 'permission']);
    Route::post('/parent-dept/update/{id}', [ParentDeptController::class, 'update'])->name('parent-dept.update')->middleware(['auth', 'permission']);
    Route::post('/parent-dept/destroy/{id}', [ParentDeptController::class, 'destroy'])->name('parent-dept.destroy')->middleware(['auth', 'permission']);

    // DEPT
    Route::get('/dept/index', [DeptController::class, 'index'])->name('dept.index')->middleware(['auth', 'permission']);
    Route::get('/dept/template', [DeptController::class, 'exportTemplate'])->name('dept.template')->middleware(['auth', 'permission']);
    Route::get('/dept/export', [DeptController::class, 'exportData'])->name('dept.export')->middleware(['auth', 'permission']);
    Route::post('/dept/import', [DeptController::class, 'import'])->name('dept.import')->middleware(['auth', 'permission']);
    Route::get('/dept/get-data', [DeptController::class, 'getData'])->name('dept.get-data')->middleware(['auth', 'permission']);
    Route::post('/dept/store', [DeptController::class, 'store'])->name('dept.store')->middleware(['auth', 'permission']);
    Route::post('/dept/update/{id}', [DeptController::class, 'update'])->name('dept.update')->middleware(['auth', 'permission']);
    Route::post('/dept/destroy/{id}', [DeptController::class, 'destroy'])->name('dept.destroy')->middleware(['auth', 'permission']);

    // Overtime
    Route::get('/overtime', [OvertimeController::class, 'index'])->name('overtime.index')->middleware(['auth', 'permission']);
    Route::get('/overtime/download-template', [OvertimeController::class, 'downloadTemplateOvertime'])->name('overtime.downloadTemplate')->middleware(['auth', 'permission']);
    Route::post('/overtime/import', [OvertimeController::class, 'importOvertime'])->name('overtime.import')->middleware(['auth', 'permission']);
    Route::get('/overtime/get-data', [OvertimeController::class, 'getData'])->name('overtime.get-data')->middleware(['auth', 'permission']);
    Route::get('/overtime/calendar-data', [OvertimeController::class, 'calendarDisplay'])->name('overtime.calendar-data')->middleware(['auth', 'permission']);
    Route::get('/overtime/calendar', [OvertimeController::class, 'calendarOvertime'])->name('overtime.calendar')->middleware(['auth', 'permission']);
    Route::get('/overtime/export', [OvertimeController::class, 'exportCalendar'])->name('overtime.export')->middleware(['auth', 'permission']);
    Route::get('/overtime/export-template', [OvertimeController::class, 'exportCalendarTemplate'])->name('overtime.export-template')->middleware(['auth', 'permission']);
    Route::post('/overtime/update/{id}', [OvertimeController::class, 'update'])->name('overtime.update')->middleware(['auth', 'permission']);
    Route::delete('/overtime/delete/{id}', [OvertimeController::class, 'destroy'])->name('overtime.destroy')->middleware(['auth', 'permission']);
    Route::post('/overtime/delete-all', [OvertimeController::class, 'destroyAll'])->name('overtime.destroyAll')->middleware(['auth', 'permission']);

    // Late Compensation
    Route::prefix('late-compensation')->name('late-compensation.')->group(function () {
        Route::get('/', [LateCompensationController::class, 'index'])->name('index')->middleware(['auth', 'permission']);
        Route::get('/create', [LateCompensationController::class, 'create'])->name('create')->middleware(['auth', 'permission']);
        Route::post('/store', [LateCompensationController::class, 'store'])->name('store')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [LateCompensationController::class, 'edit'])->name('edit')->middleware(['auth', 'permission']);
        Route::post('/update/{id}', [LateCompensationController::class, 'update'])->name('update')->middleware(['auth', 'permission']);
        Route::get('/delete/{id}', [LateCompensationController::class, 'delete'])->name('delete')->middleware(['auth', 'permission']);
    });

    // ========================================
    // APPROVAL RULES (Master)
    // ========================================
    Route::prefix('approval')->middleware('permission')->group(function () {
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
    Route::prefix('leave-recap')->middleware('permission')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminLeaveRecapController::class, 'index'])->name('leave-recap.index');
        Route::get('/get-data', [\App\Http\Controllers\AdminLeaveRecapController::class, 'getData'])->name('leave-recap.get-data');
        Route::get('/detail/{token}', [\App\Http\Controllers\AdminLeaveRecapController::class, 'getDetail'])->name('leave-recap.detail');
    });

    // ========================================
    // LEAVE BALANCES (Admin)
    // ========================================
    Route::prefix('leave-balances')->middleware('permission')->group(function () {
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
        Route::get('/export-excel', [AdminReportController::class, 'exportExcel'])->name('report.export-excel');
    });

    // ========================================
    // POLIKLINIK — Dokter Antrian
    // ========================================
    Route::prefix('dokter')->middleware('role:Dokter|Admin')->group(function () {
        Route::get('/antrian', [DokterAntrianController::class, 'index'])->name('dokter.antrian');
        Route::post('/mulai-periksa/{id}', [DokterAntrianController::class, 'mulaiPeriksa'])->name('dokter.mulai-periksa');
        Route::get('/periksa/{id}', [DokterAntrianController::class, 'formPeriksa'])->name('dokter.periksa');
        Route::post('/selesai-periksa/{id}', [DokterAntrianController::class, 'selesaiPeriksa'])->name('dokter.selesai-periksa');
        Route::get('/resep/{id}', [DokterAntrianController::class, 'getResep'])->name('dokter.get-resep');
        Route::post('/simpan-qty-obat/{id}', [DokterAntrianController::class, 'simpanQtyObat'])->name('dokter.simpan-qty-obat');
    });

    // Recruitment
    Route::get('/recruitment/index', [RecruitmentController::class, 'index'])->name('recruitment.index')->middleware(['auth', 'permission']);
    Route::post('/recruitment/send-whatsapp', [RecruitmentController::class, 'sendWhatsApp'])->name('recruitment.sendWhatsApp')->middleware(['auth', 'permission']);
    Route::post('/recruitment/update-penilaian', [RecruitmentController::class, 'updatePenilaian'])->name('recruitment.updatePenilaian')->middleware(['auth', 'permission']);
    Route::get('/recruitment/edit/{id}', [RecruitmentController::class, 'edit'])->name('recruitment.edit')->middleware(['auth', 'permission']);
    Route::put('/recruitment/update/{id}', [RecruitmentController::class, 'update'])->name('recruitment.update')->middleware(['auth', 'permission']);


    /*
    |--------------------------------------------------------------------------
    | WHATSAPP DEVICE
    |--------------------------------------------------------------------------
    */

    Route::get('/devices', [WhatsappDeviceController::class, 'index'])->name('devices.index')->middleware(['auth', 'permission']);
    Route::get('/devices/create', [WhatsappDeviceController::class, 'create'])->name('devices.create')->middleware(['auth', 'permission']);
    Route::post('/devices/store', [WhatsappDeviceController::class, 'store'])->name('devices.store')->middleware(['auth', 'permission']);
    Route::get('/devices/edit/{id}', [WhatsappDeviceController::class, 'edit'])->name('devices.edit')->middleware(['auth', 'permission']);
    Route::post('/devices/update/{id}', [WhatsappDeviceController::class, 'update'])->name('devices.update')->middleware(['auth', 'permission']);
    Route::delete('/devices/destroy/{id}', [WhatsappDeviceController::class, 'destroy'])->name('devices.destroy')->middleware(['auth', 'permission']);
    Route::get('/devices/status/{id}', [WhatsappDeviceController::class, 'checkStatus'])->name('devices.status')->middleware(['auth', 'permission']);
    Route::get('devices/{id}/qr', [WhatsappDeviceController::class, 'qr'])->name('devices.qr')->middleware(['auth', 'permission']);
    Route::post('/devices/{device}/disconnect', [WhatsappDeviceController::class, 'disconnect'])->name('devices.disconnect')->middleware(['auth', 'permission']);

    Route::prefix('job-vacancy')
        ->name('job-vacancy.')
        ->middleware(['auth', 'permission'])
        ->group(function () {
            Route::get('/', [JobVacancyController::class, 'index'])->name('index');
            Route::get('/data', [JobVacancyController::class, 'data'])->name('data');
            Route::post('/', [JobVacancyController::class, 'store'])->name('store');
            Route::get('/{jobVacancy}/edit', [JobVacancyController::class, 'edit'])->name('edit');
            Route::put('/{jobVacancy}', [JobVacancyController::class, 'update'])->name('update');
            Route::delete('/{jobVacancy}', [JobVacancyController::class, 'destroy'])->name('destroy');
            Route::patch('/{jobVacancy}/toggle-status', [JobVacancyController::class, 'toggleStatus'])->name('toggle-status');
            Route::get('/{jobVacancy}/applicants', [JobVacancyController::class, 'applicants'])->name('applicants');
        });

    /*
|--------------------------------------------------------------------------
| Canteen Management
|--------------------------------------------------------------------------
*/
    Route::get('/canteens', [CanteenController::class, 'index'])->middleware(['auth', 'permission'])->name('canteens.index');
    Route::get('/canteens/create', [CanteenController::class, 'create'])->middleware(['auth', 'permission'])->name('canteens.create');
    Route::post('/canteens', [CanteenController::class, 'store'])->middleware(['auth', 'permission'])->name('canteens.store');
    Route::get('/canteens/{id}/edit', [CanteenController::class, 'edit'])->middleware(['auth', 'permission'])->name('canteens.edit');
    Route::put('/canteens/{id}', [CanteenController::class, 'update'])->middleware(['auth', 'permission'])->name('canteens.update');
    Route::delete('/canteens/{id}', [CanteenController::class, 'destroy'])->middleware(['auth', 'permission'])->name('canteens.delete');

    /*
    |--------------------------------------------------------------------------
    | Food Menu Management (Catering / Admin)
    |--------------------------------------------------------------------------
    */
    Route::get('/food-menus', [FoodMenuController::class, 'index'])->middleware(['auth', 'permission'])->name('food-menus.index');
    Route::get('/food-menus/create', [FoodMenuController::class, 'create'])->middleware(['auth', 'permission'])->name('food-menus.create');
    Route::post('/food-menus', [FoodMenuController::class, 'store'])->middleware(['auth', 'permission'])->name('food-menus.store');
    Route::get('/food-menus/{id}/edit', [FoodMenuController::class, 'edit'])->middleware(['auth', 'permission'])->name('food-menus.edit');
    Route::put('/food-menus/{id}', [FoodMenuController::class, 'update'])->middleware(['auth', 'permission'])->name('food-menus.update');
    Route::delete('/food-menus/{id}', [FoodMenuController::class, 'destroy'])->middleware(['auth', 'permission'])->name('food-menus.delete');


    /*
    |--------------------------------------------------------------------------
    | Order Recap (Catering / Admin) - lihat berapa makanan yang dipesan
    |--------------------------------------------------------------------------
    */
    Route::get('/food-orders/recap', [FoodOrderController::class, 'recap'])->middleware(['auth', 'permission'])->name('food-orders.recap');
    Route::get('/food-orders/recap/data', [FoodOrderController::class, 'recapData'])->middleware(['auth', 'permission'])->name('food-orders.recap.data');


    /*
    |--------------------------------------------------------------------------
    | WHATSAPP TEMPLATE
    |--------------------------------------------------------------------------
    */

    Route::get('/templates', [WhatsappTemplateController::class, 'index'])->name('templates.index')->middleware(['auth', 'permission']);
    Route::get('/templates/create', [WhatsappTemplateController::class, 'create'])->name('templates.create')->middleware(['auth', 'permission']);
    Route::post('/templates/store', [WhatsappTemplateController::class, 'store'])->name('templates.store')->middleware(['auth', 'permission']);
    Route::get('/templates/edit/{id}', [WhatsappTemplateController::class, 'edit'])->name('templates.edit')->middleware(['auth', 'permission']);
    Route::post('/templates/update/{id}', [WhatsappTemplateController::class, 'update'])->name('templates.update')->middleware(['auth', 'permission']);
    Route::get('/templates/destroy/{id}', [WhatsappTemplateController::class, 'destroy'])->name('templates.destroy')->middleware(['auth', 'permission']);

    /*
    |--------------------------------------------------------------------------
    | SEND WHATSAPP
    |--------------------------------------------------------------------------
    */

    Route::get('/send/create', [WhatsappSendController::class, 'create'])->name('send.create')->middleware(['auth', 'permission']);
    Route::get('/send-whatsapp', [WhatsappSendController::class, 'create'])->name('send.create')->middleware(['auth', 'permission']);
    Route::post('/send-template', [WhatsappSendController::class, 'sendTemplate'])->name('send-template')->middleware(['auth', 'permission']);

    Route::get('/applicant-contact', [ApplicantContactController::class, 'index'])->name('applicant-contact.index');
    Route::get('/applicant-contact/send', [ApplicantContactController::class, 'send'])->name('applicant-contact.send');
    Route::get('/applicant-contact/create', [ApplicantContactController::class, 'create'])->name('applicant-contact.create');
    Route::post('/applicant-contact/store', [ApplicantContactController::class, 'store'])->name('applicant-contact.store');
    Route::delete('/applicant-contact/{id}', [ApplicantContactController::class, 'destroy'])->name('applicant-contact.destroy');

    Route::prefix('expat')->group(function () {

        Route::get('master/index', [ExpatController::class, 'indexMaster'])->name('expat.master.index')->middleware(['auth', 'permission']);
        Route::get('master/create', [ExpatController::class, 'createMaster'])->name('expat.master.create')->middleware(['auth', 'permission']);
        Route::get('master/edit/{id}', [ExpatController::class, 'editMaster'])->name('expat.master.edit')->middleware(['auth', 'permission']);
        Route::get('master/delete/{id}', [ExpatController::class, 'deleteMaster'])->name('expat.master.delete')->middleware(['auth', 'permission']);
        Route::post('master/store', [ExpatController::class, 'storeMaster'])->name('expat.master.store')->middleware(['auth', 'permission']);
        Route::post('master/update/{id}', [ExpatController::class, 'updateMaster'])->name('expat.master.update')->middleware(['auth', 'permission']);
        Route::post('import/master', [ExpatController::class, 'importMaster'])->name('expat.import.master')->middleware(['auth', 'permission']);
        Route::get('template/master', [ExpatController::class, 'templateMaster'])->name('expat.template.master')->middleware(['auth', 'permission']);

        Route::get('onleave/index', [ExpatController::class, 'indexOnleave'])->name('expat.onleave.index')->middleware(['auth', 'permission']);
        Route::get('onleave/create', [ExpatController::class, 'createOnLeave'])->name('expat.onleave.create')->middleware(['auth', 'permission']);
        Route::post('onleave/store', [ExpatController::class, 'storeOnleave'])->name('expat.onleave.store')->middleware(['auth', 'permission']);
        Route::get('onleave/edit/{id}', [ExpatController::class, 'editOnleave'])->name('expat.onleave.edit')->middleware(['auth', 'permission']);
        Route::post('onleave/update/{id}', [ExpatController::class, 'updateOnleave'])->name('expat.onleave.update')->middleware(['auth', 'permission']);
        Route::get('onleave/delete/{id}', [ExpatController::class, 'deleteOnleave'])->name('expat.onleave.delete')->middleware(['auth', 'permission']);
        Route::post('import/onleave', [ExpatController::class, 'importOnleave'])->name('expat.import.onleave')->middleware(['auth', 'permission']);
        Route::get('template/onleave', [ExpatController::class, 'templateOnleave'])->name('expat.template.onleave')->middleware(['auth', 'permission']);

        Route::get('cost/index', [ExpatController::class, 'indexCost'])->name('expat.cost.index')->middleware(['auth', 'permission']);
        Route::get('cost/create', [ExpatController::class, 'createCost'])->name('expat.cost.create')->middleware(['auth', 'permission']);
        Route::post('cost/store', [ExpatController::class, 'storeCost'])->name('expat.cost.store')->middleware(['auth', 'permission']);
        Route::get('cost/edit/{id}', [ExpatController::class, 'editCost'])->name('expat.cost.edit')->middleware(['auth', 'permission']);
        Route::post('cost/update/{id}', [ExpatController::class, 'updateCost'])->name('expat.cost.update')->middleware(['auth', 'permission']);
        Route::get('cost/delete/{id}', [ExpatController::class, 'deleteCost'])->name('expat.cost.delete')->middleware(['auth', 'permission']);
        Route::post('import/cost', [ExpatController::class, 'importCost'])->name('expat.import.cost')->middleware(['auth', 'permission']);
        Route::get('template/cost', [ExpatController::class, 'templateCost'])->name('expat.template.cost')->middleware(['auth', 'permission']);

        Route::get('expat/rekap/export', [ExpatController::class, 'exportRekap'])->name('expat.rekap.export')->middleware(['auth', 'permission']);
    });

    Route::prefix('expat-dashboard')->name('expat-dashboard.')->middleware(['auth', 'permission'])->group(function () {
        Route::get('/', [ExpatController::class, 'dashboard'])->name('index');
        Route::get('/chart-data', [ExpatController::class, 'chartData'])->name('chart-data');
        Route::get('/recap-data', [ExpatController::class, 'recapData'])->name('recap-data');
        Route::get('/document-data', [ExpatController::class, 'documentData'])->name('document-data');
        Route::get('/search-employee', [ExpatController::class, 'searchEmployee'])->name('search-employee');
        Route::get('/transaction-detail', [ExpatController::class, 'transactionDetail'])->name('transaction-detail');
    });

    // Foreign Guest dashboard data
    Route::get('/guest-master/dashboard-data', [ForeignGuestController::class, 'dashboardData'])->name('guest-master.dashboard-data')->middleware(['auth', 'permission']);
    Route::get('/guest-master/dashboard-detail/{id}', [ForeignGuestController::class, 'dashboardDetail'])->name('guest-master.dashboard-detail')->middleware(['auth', 'permission']);

    // Chu Family dashboard data
    Route::get('/chu-family/dashboard-data', [ChuFamilyController::class, 'dashboardData'])->name('chu-family.dashboard-data')->middleware(['auth', 'permission']);
    Route::get('/chu-family/dashboard-detail/{id}', [ChuFamilyController::class, 'dashboardDetail'])->name('chu-family.dashboard-detail')->middleware(['auth', 'permission']);

    Route::prefix('chu-family')->group(function () {
        Route::get('/', [ChuFamilyController::class, 'index'])->name('chu-family.index')->middleware(['auth', 'permission']);
        Route::get('/create', [ChuFamilyController::class, 'create'])->name('chu-family.create')->middleware(['auth', 'permission']);
        Route::post('/store', [ChuFamilyController::class, 'store'])->name('chu-family.store')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [ChuFamilyController::class, 'edit'])->name('chu-family.edit')->middleware(['auth', 'permission']);
        Route::put('/update/{id}', [ChuFamilyController::class, 'update'])->name('chu-family.update')->middleware(['auth', 'permission']);
        Route::get('/delete/{id}', [ChuFamilyController::class, 'delete'])->name('chu-family.delete')->middleware(['auth', 'permission']);
        Route::post('/import', [ChuFamilyController::class, 'import'])->name('chu-family.import')->middleware(['auth', 'permission']);
        Route::get('/template', [ChuFamilyController::class, 'template'])->name('chu-family.template')->middleware(['auth', 'permission']);
        Route::get('/export', [ChuFamilyController::class, 'export'])->name('chu-family.export')->middleware(['auth', 'permission']);
    });

    Route::prefix('epo')->group(function () {
        Route::get('/index', [EpoController::class, 'index'])->name('epo.index')->middleware(['auth', 'permission']);
        Route::get('/create', [EpoController::class, 'create'])->name('epo.create')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [EpoController::class, 'edit'])->name('epo.edit')->middleware(['auth', 'permission']);
        Route::post('/store', [EpoController::class, 'store'])->name('epo.store')->middleware(['auth', 'permission']);
        Route::put('/{epo}', [EpoController::class, 'update'])->name('epo.update')->middleware(['auth', 'permission']);
        Route::get('/{epo}', [EpoController::class, 'destroy'])->name('epo.delete')->middleware(['auth', 'permission']);
        Route::post('/import', [EpoController::class, 'import'])->name('epo.import')->middleware(['auth', 'permission']);
        Route::get('/template', [EpoController::class, 'template'])->name('epo.template')->middleware(['auth', 'permission']);
        Route::get('/export', [EpoController::class, 'export'])->name('epo.export')->middleware(['auth', 'permission']);
    });

    Route::prefix('foreign-guest')->group(function () {

        Route::get('/', [ForeignGuestController::class, 'index'])->name('foreign-guest.index')->middleware(['auth', 'permission']);
        Route::get('/create', [ForeignGuestController::class, 'create'])->name('foreign-guest.create')->middleware(['auth', 'permission']);
        Route::post('/store', [ForeignGuestController::class, 'store'])->name('foreign-guest.store')->middleware(['auth', 'permission']);
        Route::get('/{id}', [ForeignGuestController::class, 'show'])->name('foreign-guest.show')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [ForeignGuestController::class, 'edit'])->name('foreign-guest.edit')->middleware(['auth', 'permission']);
        Route::put('/update/{id}', [ForeignGuestController::class, 'update'])->name('foreign-guest.update')->middleware(['auth', 'permission']);
        Route::get('/delete/{id}', [ForeignGuestController::class, 'destroy'])->name('foreign-guest.delete')->middleware(['auth', 'permission']);
    });

    Route::prefix('guest-master')->group(function () {

        Route::get('/', [GuestMasterController::class, 'index'])->name('guest-master.index')->middleware(['auth', 'permission']);
        Route::get('/create', [GuestMasterController::class, 'create'])->name('guest-master.create')->middleware(['auth', 'permission']);
        Route::post('/store', [GuestMasterController::class, 'store'])->name('guest-master.store')->middleware(['auth', 'permission']);
        Route::get('/{id}', [GuestMasterController::class, 'show'])->name('guest-master.show')->middleware(['auth', 'permission']);
        Route::get('/edit/{id}', [GuestMasterController::class, 'edit'])->name('guest-master.edit')->middleware(['auth', 'permission']);
        Route::post('/update/', [GuestMasterController::class, 'update'])->name('guest-master.update')->middleware(['auth', 'permission']);
        Route::get('/delete/{id}', [GuestMasterController::class, 'destroy'])->name('guest-master.delete')->middleware(['auth', 'permission']);
        Route::get('/guest-master/export', [GuestMasterController::class, 'export'])->name('guest-master.export')->middleware(['auth', 'permission']);
    });

    // Compensation
    Route::get('compensation', [CompensationsController::class, 'index'])->name('compensation.index')->middleware(['auth', 'permission']);
    Route::post('compensation/generate', [CompensationsController::class, 'generate'])->name('compensation.generate')->middleware(['auth', 'permission']);
    Route::post('compensation/recap', [CompensationApproveController::class, 'createCompensationCSV'])->name('compensation.recap')->middleware(['auth', 'permission']);
    Route::get('/compensation/details/{date}', [CompensationsController::class, 'details'])->name('compensation.details')->middleware(['auth', 'permission']);
    Route::post('/compensation/check', [CompensationsController::class, 'check'])->name('compensation.check')->middleware(['auth', 'permission']);
    // Route::get('/compensation/check/{date}', [CompensationsController::class, 'check'])->name('compensation.check');
    Route::delete('/compensation/{id}', [CompensationsController::class, 'destroy'])->name('compensation.destroy')->middleware(['auth', 'permission']);

    Route::prefix('health-test')->group(function () {
        Route::get('/', [HealthTestController::class, 'index'])->name('health-test.index');
        Route::get('/create', [HealthTestController::class, 'create'])->name('health-test.create');
        Route::post('/store', [HealthTestController::class, 'store'])->name('health-test.store');
        Route::get('/delete/{id}', [HealthTestController::class, 'delete'])->name('health-test.delete');
        Route::get('/pdf/{nik}', [HealthTestController::class, 'downloadPdf'])->name('health-test.pdf');
        Route::get('/{id}/edit', [HealthTestController::class, 'edit'])->name('health-test.edit');
        Route::put('/{id}', [HealthTestController::class, 'update'])->name('health-test.update');
    });

    /*
    |--------------------------------------------------------------------------
    | SHIFT
    |--------------------------------------------------------------------------
    */

    Route::get('/shift', [ShiftController::class, 'index'])->name('shift.index')->middleware(['auth', 'permission']);
    Route::get('/shift/create', [ShiftController::class, 'create'])->name('shift.create')->middleware(['auth', 'permission']);
    Route::post('/shift/store', [ShiftController::class, 'store'])->name('shift.store')->middleware(['auth', 'permission']);
    Route::get('/shift/edit/{id}', [ShiftController::class, 'edit'])->name('shift.edit')->middleware(['auth', 'permission']);
    Route::put('/shift/update/{id}', [ShiftController::class, 'update'])->name('shift.update')->middleware(['auth', 'permission']);
    Route::get('/shift/delete/{id}', [ShiftController::class, 'delete'])->name('shift.delete')->middleware(['auth', 'permission']);

    Route::get('/employee-mutations', [EmployeeMutationController::class, 'index'])->name('employee-mutations.index');
    Route::post('/employee-mutations', [EmployeeMutationController::class, 'store'])->name('employee-mutations.store');
    Route::get('/employee-mutations/{id}', [EmployeeMutationController::class, 'show'])->name('employee-mutations.show');
    Route::put('/employee-mutations/{id}', [EmployeeMutationController::class, 'update'])->name('employee-mutations.update');
    Route::delete('/employee-mutations/{id}', [EmployeeMutationController::class, 'destroy'])->name('employee-mutations.destroy');


    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SHIFT
    |--------------------------------------------------------------------------
    */

    Route::get('/employee-shift', [EmployeeShiftController::class, 'index'])->name('employee-shift.index');
    Route::get('/employee-shift/template', [EmployeeShiftController::class, 'exportTemplate'])->name('employee-shift.template');
    Route::post('/employee-shift/import', [EmployeeShiftController::class, 'importTemplate'])->name('employee-shift.import');
    Route::get('/employee-shift/create', [EmployeeShiftController::class, 'create'])->name('employee-shift.create');
    Route::get('/employee-shift/edit/{id}', [EmployeeShiftController::class, 'edit'])->name('employee-shift.edit');
    Route::put('/employee-shift/update/{id}', [EmployeeShiftController::class, 'update'])->name('employee-shift.update');
    Route::post('/employee-shift/store', [EmployeeShiftController::class, 'store'])->name('employee-shift.store');
    Route::get('/employee-shift/delete/{id}', [EmployeeShiftController::class, 'delete'])->name('employee-shift.delete');
});

Route::prefix('insentif-threshold')->group(function () {
    Route::get('/', [InsentifThresholdController::class, 'index'])->name('insentif.threshold.index')->middleware(['auth', 'permission']);
    Route::get('/create', [InsentifThresholdController::class, 'create'])->name('insentif.threshold.create')->middleware(['auth', 'permission']);
    Route::post('/store', [InsentifThresholdController::class, 'store'])->name('insentif.threshold.store')->middleware(['auth', 'permission']);
    Route::get('/edit/{id}', [InsentifThresholdController::class, 'edit'])->name('insentif.threshold.edit')->middleware(['auth', 'permission']);
    Route::post('/update/{id}', [InsentifThresholdController::class, 'update'])->name('insentif.threshold.update')->middleware(['auth', 'permission']);
    Route::get('/delete/{id}', [InsentifThresholdController::class, 'delete'])->name('insentif.threshold.delete')->middleware(['auth', 'permission']);
});

Route::prefix('sewing-violations')->group(function () {
    Route::get('/', [SewingViolationController::class, 'index'])->name('sewing-violations.index');
    Route::get('/create', [SewingViolationController::class, 'create'])->name('sewing-violations.create');
    Route::post('/store', [SewingViolationController::class, 'store'])->name('sewing-violations.store');
    Route::get('/edit/{id}', [SewingViolationController::class, 'edit'])->name('sewing-violations.edit');
    Route::post('/update', [SewingViolationController::class, 'update'])->name('sewing-violations.update');
    Route::get('/delete/{id}', [SewingViolationController::class, 'delete'])->name('sewing-violations.delete');
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

// Insentif 6S
Route::get('/employee-6s-assignment', [Employee6sAssignmentController::class, 'index'])->name('employee6s.index')->middleware(['auth', 'permission']);
Route::get('/employee-6s-assignment/create', [Employee6sAssignmentController::class, 'create'])->name('employee6s.create')->middleware(['auth', 'permission']);
Route::post('/employee-6s-assignment/store', [Employee6sAssignmentController::class, 'store'])->name('employee6s.store')->middleware(['auth', 'permission']);
Route::get('/employee-6s-assignment/edit/{id}', [Employee6sAssignmentController::class, 'edit'])->name('employee6s.edit')->middleware(['auth', 'permission']);
Route::put('/employee-6s-assignment/update/{id}', [Employee6sAssignmentController::class, 'update'])->name('employee6s.update')->middleware(['auth', 'permission']);
Route::get('/employee-6s-assignment/delete/{id}', [Employee6sAssignmentController::class, 'destroy'])->name('employee6s.destroy')->middleware(['auth', 'permission']);
Route::get('/employee-6s-assignment/{period}/check', [Employee6sAssignmentController::class, 'check'])->name('employee6s.check')->middleware(['auth', 'permission']);

Route::prefix('employee-violation')->name('employee-violation.')->group(function () {
    Route::get('/', [EmployeeViolationController::class, 'index'])->name('index')->middleware(['auth', 'permission']);
    Route::get('/create', [EmployeeViolationController::class, 'create'])->name('create')->middleware(['auth', 'permission']);
    Route::post('/store', [EmployeeViolationController::class, 'store'])->name('store')->middleware(['auth', 'permission']);
    Route::get('/edit/{id}', [EmployeeViolationController::class, 'edit'])->name('edit')->middleware(['auth', 'permission']);
    Route::put('/update/{id}', [EmployeeViolationController::class, 'update'])->name('update')->middleware(['auth', 'permission']);
    Route::get('/delete/{id}', [EmployeeViolationController::class, 'delete'])->name('delete')->middleware(['auth', 'permission']);
});

Route::prefix('bpjs-exceptions')->name('bpjs-exceptions.')->middleware(['auth', 'permission'])->group(function () {

    Route::get('/', [BpjsExceptionController::class, 'index'])->name('index');
    Route::get('/create', [BpjsExceptionController::class, 'create'])->name('create');
    Route::post('/store', [BpjsExceptionController::class, 'store'])->name('store');
    Route::get('/edit/{id}', [BpjsExceptionController::class, 'edit'])->name('edit');
    Route::put('/update/{id}', [BpjsExceptionController::class, 'update'])->name('update');
    Route::delete('/delete/{id}', [BpjsExceptionController::class, 'destroy'])->name('destroy');
});


Route::prefix('payroll/recap')->name('payroll-recap.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('/', [PayrollRecapController::class, 'index'])->name('index');
    Route::get('/chart-data', [PayrollRecapController::class, 'chartData'])->name('chart-data');
    Route::get('/search-employee', [PayrollRecapController::class, 'searchEmployee'])->name('search-employee');
    Route::get('/detail-data', [PayrollRecapController::class, 'detailData'])->name('detail-data');
    Route::get('/overtime-data', [PayrollRecapController::class, 'overtimeData'])->name('overtime-data');
});

Route::prefix('role-payroll')->name('role-payroll.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('/', [RolePayrollController::class, 'index'])->name('index');
    Route::post('/', [RolePayrollController::class, 'store'])->name('store');
    Route::get('/{id}/users-for-edit', [RolePayrollController::class, 'usersForEdit'])->name('users-for-edit');
    Route::put('/{id}', [RolePayrollController::class, 'update'])->name('update');
    Route::delete('/{id}', [RolePayrollController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission']) // ganti/tambahkan middleware role admin sesuai project Anda
    ->group(function () {
        Route::get('/admin/qr-devices', [QrDeviceController::class, 'index'])->name('qr-devices.index');
        Route::post('/admin/qr-devices', [QrDeviceController::class, 'store'])->name('qr-devices.store');
        Route::put('/admin/qr-devices/{qrDevice}', [QrDeviceController::class, 'update'])->name('qr-devices.update');
        Route::patch('/admin/qr-devices/{qrDevice}/toggle', [QrDeviceController::class, 'toggle'])->name('qr-devices.toggle');
        Route::delete('/admin/qr-devices/{qrDevice}', [QrDeviceController::class, 'destroy'])->name('qr-devices.destroy');
        Route::patch('/admin/qr-devices/{qrDevice}/rename', [QrDeviceController::class, 'rename'])->name('qr-devices.rename');
        Route::delete('qr-devices/pending/{uuid}', [QrDeviceController::class, 'destroyPendingAttempt'])->name('qr-devices.pending.destroy');
    });

Route::prefix('doorprize')
    ->name('doorprize.')
    ->middleware(['auth', 'permission'])
    ->group(function () {

        // Scan QR NPK
        Route::get('/scan', [DoorprizeController::class, 'scanPage'])->name('scan');
        Route::post('/scan', [DoorprizeController::class, 'storeScan'])->name('scan.store');

        // Undian doorprize
        Route::get('/draw', [DoorprizeController::class, 'drawPage'])->name('draw');
        Route::post('/draw', [DoorprizeController::class, 'draw'])->name('draw.run');
        Route::get('/winners', [DoorprizeController::class, 'winnersList'])->name('winners');
        Route::post('/winners/{winner}/void', [DoorprizeController::class, 'voidWinner'])->name('winners.void');

        // Reset data (sebaiknya dibatasi role admin saja)
        Route::post('/reset-scans', [DoorprizeController::class, 'resetScans'])->name('reset-scans');
        Route::post('/reset-winners', [DoorprizeController::class, 'resetWinners'])->name('reset-winners');
    });


Route::prefix('monitoring')->middleware(['auth', 'permission'])->group(function () {
    Route::get('/', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/api/stats', [MonitoringController::class, 'stats'])->name('monitoring.stats');
});


Route::get('/employee-late', [EmployeeLateController::class, 'index'])->name('employee-late.index')->middleware(['auth', 'permission']);
Route::get('/employee-late/create', [EmployeeLateController::class, 'create'])->name('employee-late.create')->middleware(['auth', 'permission']);
Route::post('/employee-late', [EmployeeLateController::class, 'store'])->name('employee-late.store')->middleware(['auth', 'permission']);
Route::get('/employee-late/{id}/edit', [EmployeeLateController::class, 'edit'])->name('employee-late.edit')->middleware(['auth', 'permission']);
Route::put('/employee-late/{id}', [EmployeeLateController::class, 'update'])->name('employee-late.update')->middleware(['auth', 'permission']);
Route::delete('/employee-late/{id}', [EmployeeLateController::class, 'destroy'])->name('employee-late.delete')->middleware(['auth', 'permission']);
Route::get('/employee-late/template', [EmployeeLateController::class, 'template'])->name('employee-late.template')->middleware(['auth', 'permission']);
Route::post('/employee-late/import', [EmployeeLateController::class, 'import'])->name('employee-late.import')->middleware(['auth', 'permission']);
Route::get('/employee-late/search-npk', [EmployeeLateController::class, 'searchNpk'])->name('employee-late.search-npk')->middleware(['auth', 'permission']);

// Payroll 
Route::get('/payroll/calculate', [PayrollController::class, 'calculate'])->name('payroll.calculate');
// Route::get('/payroll-process/process', [PayrollProcessController::class, 'process'])->name('payroll-process.process');

// Employee Payroll
Route::post('/employee-payroll/{npk}/show-slip', [EmployeePayrollController::class, 'showSlip'])->name('employee-payroll.show-slip');
Route::get('/employee-payroll/show-audit/{run_id}/{npk}', [EmployeePayrollController::class, 'showSlipAudit'])->name('employee-payroll.view-slip-audit');
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

Route::prefix('monitoring')->name('monitoring.')->middleware(['auth', 'permission'])->group(function () {
    Route::get('/dashboard', [MonitoringDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [MonitoringDashboardController::class, 'data'])->name('dashboard.data');

    Route::get('/order/import', [OrderImportController::class, 'form'])->name('order.import.form');
    Route::post('/order/import', [OrderImportController::class, 'store'])->name('order.import.store');
    Route::get('order-import/progress/{batchId}', [OrderImportController::class, 'progress'])
        ->name('order.import.progress');
    Route::post('sync-bom', [MonitoringDashboardController::class, 'syncBom'])->name('sync.bom');
    Route::post('sync-po', [MonitoringDashboardController::class, 'syncPo'])->name('sync.po');
    Route::get('dashboard/calendar', [MonitoringDashboardController::class, 'calendar'])->name('dashboard.calendar');
    Route::get('dashboard/calendar-detail', [MonitoringDashboardController::class, 'calendarDetail'])->name('dashboard.calendar.detail');

    Route::get('/rekonsiliasi', [MonitoringRekonsiliasiController::class, 'index'])->name('rekonsiliasi');
    Route::get('/rekonsiliasi/data', [MonitoringRekonsiliasiController::class, 'data'])->name('rekonsiliasi.data');
    Route::post('/rekonsiliasi/sync', [MonitoringRekonsiliasiController::class, 'syncRekonsiliasi'])->name('rekonsiliasi.sync');
    Route::post('/rekonsiliasi/sync-prod-line', [MonitoringRekonsiliasiController::class, 'syncProdLine'])->name('rekonsiliasi.sync-prod-line');
    Route::post('/rekonsiliasi/sync-shipment', [MonitoringRekonsiliasiController::class, 'syncShipment'])->name('rekonsiliasi.sync-shipment');
    Route::post('/rekonsiliasi/sync-work-order', [MonitoringRekonsiliasiController::class, 'syncWorkOrder'])->name('rekonsiliasi.sync-work-order');
    Route::post('/rekonsiliasi/sync-ms-barang', [MonitoringRekonsiliasiController::class, 'syncMsBarang'])->name('rekonsiliasi.sync-ms-barang');

    // -- Kalender "Shipment Date" (mirip kalender Production Delivery di dashboard Gabungan) --
    Route::get('rekonsiliasi/calendar', [MonitoringRekonsiliasiController::class, 'calendar'])->name('rekonsiliasi.calendar');
    Route::get('rekonsiliasi/calendar-detail', [MonitoringRekonsiliasiController::class, 'calendarDetail'])->name('rekonsiliasi.calendar.detail');

    // -- Sync Master Negara & Master Supplier (2 tabel baru, jadi total 7 tabel di Sync All) --
    Route::post('rekonsiliasi/sync-ms-negara', [MonitoringRekonsiliasiController::class, 'syncMsNegara'])->name('rekonsiliasi.sync-ms-negara');
    Route::post('rekonsiliasi/sync-ms-supplier', [MonitoringRekonsiliasiController::class, 'syncMsSupplier'])->name('rekonsiliasi.sync-ms-supplier');

    Route::get('rekonsiliasi/negara-options', [MonitoringRekonsiliasiController::class, 'negaraOptions'])->name('rekonsiliasi.negara-options');
});




Route::get('/test-reverb', function () {
    event(new NotificationEvent(
        'Test Reverb',
        'Notifikasi realtime berhasil 🚀',
        'danger'
    ));
    return 'Notification Event Sent!';
})->name('test-reverb');

Route::prefix('recruitments')->name('recruitments.')->group(function () {
    Route::get('/', fn() => redirect()->route('recruitments.step', ['step' => 1]))
        ->name('index');
    Route::get('/step/{step}', [RecruitmentFormController::class, 'show'])
        ->name('step')
        ->where('step', '[1-8]');
    Route::post('/step/{step}', [RecruitmentFormController::class, 'store'])
        ->name('step.store')
        ->where('step', '[1-8]');
    Route::get('/success', fn() => view('recruitments_form.success'))
        ->name('success');
});


Route::get('/portal-recruitment-status', [PortalRecruitmentStatusController::class, 'index'])->name('portal.recruitment-status.index');
Route::post('/portal-recruitment-status/check', [PortalRecruitmentStatusController::class, 'check'])->name('portal.recruitment-status.check');
Route::get('/lowongan', [JobVacancyController::class, 'publicIndex'])->name('job-vacancy.public');
Route::get('/lowongan/data', [JobVacancyController::class, 'publicData'])
    ->name('job-vacancy.public-data');


/*
    |--------------------------------------------------------------------------
    | Food Ordering (Karyawan)
    |--------------------------------------------------------------------------
    */
Route::get('/food-orders', [FoodOrderController::class, 'index'])->name('food-orders.index');
Route::post('/food-orders', [FoodOrderController::class, 'store'])->name('food-orders.store');
Route::delete('/food-orders/{id}', [FoodOrderController::class, 'destroy'])->name('food-orders.destroy');
Route::get('/food-orders/scan', [FoodOrderController::class, 'showScan'])->name('food-orders.scan');
Route::post('/food-orders/scan', [FoodOrderController::class, 'verifyScan'])
    ->middleware('throttle:10,1') // batasi percobaan scan/manual per menit
    ->name('food-orders.scan.verify');
Route::post('/food-orders/logout-scan', [FoodOrderController::class, 'logoutScan'])->name('food-orders.logout-scan');


/*
    |--------------------------------------------------------------------------
    | Audit Recap
    |--------------------------------------------------------------------------
    */
Route::get('/audit-recap', [AuditRecapController::class, 'index'])->name('audit-recap.index');
Route::post('/audit-recap/generate', [AuditRecapController::class, 'generate'])->name('audit-recap.generate');
Route::post('/audit-recap/export', [AuditRecapController::class, 'export'])->name('audit-recap.export');
Route::get('/audit-recap/report', [AuditRecapController::class, 'report'])->name('audit-recap.report');
Route::get('/audit-recap/export-view', [AuditRecapController::class, 'export_view'])->name('audit-recap.export-view');
Route::get('/audit-recap/export-pdf', [AuditRecapController::class, 'export_pdf'])->name('audit-recap.export-pdf');
