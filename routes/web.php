<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Public\HomeController;

use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\ReportCycleController;
use App\Http\Controllers\Admin\MonthlyReportController as AdminMonthlyReportController;

use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\MonthlyReportController as TeacherMonthlyReportController;

use App\Http\Controllers\Student\DashboardController as StudentDashboardController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.process');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Superadmin
    |--------------------------------------------------------------------------
    */

    Route::prefix('superadmin')
        ->name('superadmin.')
        ->middleware('role:superadmin')
        ->group(function () {

            Route::get('/dashboard', [
                SuperAdminDashboardController::class,
                'index',
            ])->name('dashboard');

            Route::resource('users', UserController::class)
                ->except(['destroy']);

            Route::patch('/users/{user}/status', [
                UserController::class,
                'toggleStatus',
            ])->name('users.status');
        });

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {

            Route::get('/dashboard', [
                AdminDashboardController::class,
                'index',
            ])->name('dashboard');

            Route::resource('students', StudentController::class)
                ->except(['destroy']);

            Route::resource('teachers', TeacherController::class)
                ->except(['destroy']);
        });

    /*
    |--------------------------------------------------------------------------
    | Report Cycle Management
    |--------------------------------------------------------------------------
    |
    | Admin dan Superadmin dapat mengelola siklus laporan.
    |
    */

    Route::prefix('admin/report-cycles')
        ->name('admin.report-cycles.')
        ->middleware('role:admin,superadmin')
        ->group(function () {

            Route::get('/', [
                ReportCycleController::class,
                'index',
            ])->name('index');

            Route::post('/', [
                ReportCycleController::class,
                'store',
            ])->name('store');

            Route::put('/{reportCycle}', [
                ReportCycleController::class,
                'update',
            ])->name('update');
        });

    /*
    |--------------------------------------------------------------------------
    | Monthly Report Workflow - Admin & Superadmin
    |--------------------------------------------------------------------------
    |
    | Admin cabang dan Superadmin dapat:
    | - menyetujui laporan
    | - meminta revisi laporan
    |
    | Authorization berdasarkan cabang tetap dilakukan
    | oleh MonthlyReportWorkflowService.
    |
    */

    Route::prefix('admin/reports')
        ->name('admin.reports.')
        ->middleware('role:admin,superadmin')
        ->group(function () {

            Route::post('/{monthlyReport}/approve', [
                AdminMonthlyReportController::class,
                'approve',
            ])->name('approve');

            Route::post('/{monthlyReport}/revision', [
                AdminMonthlyReportController::class,
                'requestRevision',
            ])->name('revision');
        });

    /*
    |--------------------------------------------------------------------------
    | Teacher
    |--------------------------------------------------------------------------
    */

    Route::prefix('teacher')
        ->name('teacher.')
        ->middleware('role:teacher')
        ->group(function () {

            Route::get('/dashboard', [
                TeacherDashboardController::class,
                'index',
            ])->name('dashboard');

            /*
             * Teacher mengajukan atau mengirim ulang
             * laporan bulanan.
             */
            Route::post('/reports/{monthlyReport}/submit', [
                TeacherMonthlyReportController::class,
                'submit',
            ])->name('reports.submit');
        });

    /*
    |--------------------------------------------------------------------------
    | Student
    |--------------------------------------------------------------------------
    */

    Route::prefix('student')
        ->name('student.')
        ->middleware('role:student')
        ->group(function () {

            Route::get('/dashboard', [
                StudentDashboardController::class,
                'index',
            ])->name('dashboard');
        });
});