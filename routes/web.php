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

Route::get('/', [LoginController::class, 'login'])->name('/');
// Route::get('/template/auditsewing', [TemplateController::class, 'auditsewing'])->name('template.auditsewing');
// Route::get('/template/auditnonsewing', [TemplateController::class, 'auditnonsewing'])->name('template.auditnonsewing');

Route::group(['middleware' => 'guest'], function () {
    // Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register/guest', [RegisterController::class, 'store'])->name('register.guest');

    Route::get('/login', [LoginController::class, 'login'])->name('login.guest');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login');
});

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home/get-pkwt-chart', [HomeController::class, 'getPKWTChart'])->name('home.get-pkwt-chart');
    Route::get('/home/get-recap-count', [HomeController::class, 'getRecapCount'])->name('home.get-recap-count');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    //Register
    Route::get('/register/create', [RegisterController::class, 'create'])->name('register.create')->middleware(['auth', 'role:Admin']);
    Route::post('/register', [RegisterController::class, 'storeAuth'])->name('register')->middleware(['auth', 'role:Admin']);

    // PKWT
    Route::get('/pkwt/index', [PKWTController::class, 'index'])->name('pkwt.index')->middleware(['auth', 'role:Admin']);


    // BIODATA
    Route::get('/biodata/index', [BiodataController::class, 'index'])->name('biodata.index')->middleware(['auth', 'role:Admin']);
    Route::get('/biodata/get-data', [BiodataController::class, 'getData'])->name('biodata.get-data')->middleware(['auth', 'role:Admin']);
    Route::get('/biodata/fetch-last-npk', [BiodataController::class, 'fetchLastNpk'])->name('biodata.fetch-last-npk')->middleware(['auth', 'role:Admin']);
    Route::post('/biodata/store', [BiodataController::class, 'store'])->name('biodata.store')->middleware(['auth', 'role:Admin']);
    Route::get('/biodata/edit/{NPK}', [BiodataController::class, 'edit'])->name('biodata.edit')->middleware(['auth', 'role:Admin']);
    Route::post('/biodata/update/{NPK}', [BiodataController::class, 'update'])->name('biodata.update')->middleware(['auth', 'role:Admin']);
    Route::get('/biodata/show/{NPK}', [BiodataController::class, 'show'])->name('biodata.show')->middleware(['auth', 'role:Admin']);
    Route::post('/biodata/update-photo/{NPK}', [BiodataController::class, 'updatePhoto'])->name('biodata.update-photo')->middleware(['auth', 'role:Admin']);
    Route::get('/biodata/exit/{NPK}', [BiodataController::class, 'exit'])->name('biodata.exit')->middleware(['auth', 'role:Admin']);
    Route::get('/biodata/export', [BiodataController::class, 'export'])->name('biodata.export')->middleware(['auth', 'role:Admin']);

    // PKWT
    Route::get('/pkwt/index', [PKWTController::class, 'index'])->name('pkwt.index')->middleware(['auth', 'role:Admin']);


    // Pelamar
    Route::get('/pelamar/index', [PelamarController::class, 'index'])->name('pelamar.index')->middleware(['auth', 'role:Admin']);
    Route::post('/pelamar/import', [PelamarController::class, 'import'])->name('pelamar.import')->middleware(['auth', 'role:Admin']);
    Route::post('/pelamar/assign', [PelamarController::class, 'assign'])->name('pelamar.assign')->middleware(['auth', 'role:Admin']);
    Route::get('/pelamar/detail/{id}', [PelamarController::class, 'detail'])->name('pelamar.detail')->middleware(['auth', 'role:Admin']);


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
    Route::get('/overtime', [OvertimeController::class, 'index'])->name('overtime.index')->middleware(['auth', 'role:Admin']);
    Route::get('/overtime/download-template', [OvertimeController::class, 'downloadTemplateOvertime'])->name('overtime.downloadTemplate')->middleware(['auth', 'role:Admin']);
    Route::post('/overtime/import', [OvertimeController::class, 'importOvertime'])->name('overtime.import')->middleware(['auth', 'role:Admin']);
    Route::get('/overtime/get-data', [OvertimeController::class, 'getData'])->name('overtime.get-data')->middleware(['auth', 'role:Admin']);
    Route::get('/overtime/calendar-data', [OvertimeController::class, 'calendarDisplay'])->name('overtime.calendar-data')->middleware(['auth', 'role:Admin']);
    Route::get('/overtime/calendar', [OvertimeController::class, 'calendarOvertime'])->name('overtime.calendar')->middleware(['auth', 'role:Admin']);
    Route::get('/overtime/export', [OvertimeController::class, 'exportCalendar'])->name('overtime.export')->middleware(['auth', 'role:Admin']);
    Route::post('/overtime/update/{id}', [OvertimeController::class, 'update'])->name('overtime.update')->middleware(['auth', 'role:Admin']);
    Route::delete('/overtime/delete/{id}', [OvertimeController::class, 'destroy'])->name('overtime.destroy')->middleware(['auth', 'role:Admin']);
    Route::post('/overtime/delete-all', [OvertimeController::class, 'destroyAll'])->name('overtime.destroyAll')->middleware(['auth', 'role:Admin']);
});
