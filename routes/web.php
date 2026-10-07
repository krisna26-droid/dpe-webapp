<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\LearningVideoController;
use App\Http\Controllers\LearningSkillController;
use App\Http\Controllers\TeacherMessageController;
use App\Http\Controllers\MonthlyChargeController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaymentRecapRunController;
use App\Http\Controllers\QuarterlyReportFileController;
use App\Http\Controllers\ReportShareAttemptController;
use App\Http\Controllers\MonthlyReportSkillController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicContentSectionController;
use App\Http\Controllers\ReportStatusEventController;
use App\Http\Controllers\SessionStudentController;
use App\Http\Controllers\SystemSettingController;


use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\BranchController;
use App\Http\Controllers\SuperAdmin\BranchAdminAssignmentController;
use App\Http\Controllers\SuperAdmin\TeacherBranchAssignmentController;
use App\Http\Controllers\SuperAdmin\ProgramController;
use App\Http\Controllers\SuperAdmin\StudentEnrollmentController;
use App\Http\Controllers\SuperAdmin\ClassGroupController;
use App\Http\Controllers\SuperAdmin\ClassGroupMembershipController;
use App\Http\Controllers\SuperAdmin\LessonSessionController;
use App\Http\Controllers\SuperAdmin\StudentTeacherAssignmentController;
use App\Http\Controllers\SuperAdmin\TeacherAvailabilityController;
use App\Http\Controllers\SuperAdmin\GuardianController;
use App\Http\Controllers\StudentChallengeController;


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

            Route::resource('branches', BranchController::class)
                ->except(['destroy']);

            Route::patch('/branches/{branch}/status', [
                BranchController::class,
                'toggleStatus',
            ])->name('branches.status');

            Route::resource(
                'branch-admin-assignments',
                BranchAdminAssignmentController::class
            );

            Route::resource(
                'teacher-branch-assignments',
                TeacherBranchAssignmentController::class
            );

            Route::resource('programs', ProgramController::class)
                ->except(['destroy']);

            Route::patch('/programs/{program}/status', [
                ProgramController::class,
                'toggleStatus',
            ])->name('programs.status');

            Route::resource(
                'student-enrollments',
                StudentEnrollmentController::class
            );

            Route::resource(
                'class-groups',
                ClassGroupController::class
            )->except(['destroy']);

            Route::patch(
                '/class-groups/{class_group}/status',
                [ClassGroupController::class, 'toggleStatus']
            )->name('class-groups.status');

            Route::resource(
                'class-group-memberships',
                ClassGroupMembershipController::class
            )->except(['destroy']);

            Route::resource(
                'lesson-sessions',
                LessonSessionController::class
            )->except(['destroy']);

            Route::resource(
                'student-teacher-assignments',
                StudentTeacherAssignmentController::class
            )->except(['destroy']);

            Route::resource(
                'teacher-availability',
                TeacherAvailabilityController::class
            )->except(['destroy']);

            Route::resource(
                'guardians',
                GuardianController::class
            );

            Route::resource(
                'student-challenges',
                StudentChallengeController::class
            );

            Route::resource(
                'learning-videos',
                LearningVideoController::class
            );

            Route::resource(
                'learning-skills',
                LearningSkillController::class
            );

            Route::resource(
                'teacher-messages',
                TeacherMessageController::class
            );

            Route::resource(
                'monthly-charges',
                MonthlyChargeController::class
            );

            Route::post(
                '/payment-proofs',
                [PaymentProofController::class, 'store']
            )->name('payment-proofs.store');

            Route::get(
                '/payment-proofs/{paymentProof}',
                [PaymentProofController::class, 'show']
            )->name('payment-proofs.show');

            Route::get(
                '/monthly-charges/{charge}/payment-proofs',
                [PaymentProofController::class, 'byCharge']
            )->name('monthly-charges.payment-proofs');

            Route::patch(
                '/payment-proofs/{paymentProof}/review',
                [PaymentProofController::class, 'review']
            )->name('payment-proofs.review');

            Route::post('/payment-recap-runs', [PaymentRecapRunController::class, 'store'])
                ->name('payment-recap-runs.store');

            Route::get('/payment-recap-runs/{paymentRecapRun}', [PaymentRecapRunController::class, 'show'])
                ->name('payment-recap-runs.show');

            Route::get('/branches/{branch}/payment-recap-runs', [PaymentRecapRunController::class, 'byBranch'])
                ->name('branches.payment-recap-runs');

            Route::patch('/payment-recap-runs/{paymentRecapRun}/generate', [PaymentRecapRunController::class, 'markGenerated'])
                ->name('payment-recap-runs.generate');

            Route::post(
                '/quarterly-report-files',
                [QuarterlyReportFileController::class, 'store']
            )->name('quarterly-report-files.store');

            Route::get(
                '/quarterly-report-files/{id}',
                [QuarterlyReportFileController::class, 'show']
            )->name('quarterly-report-files.show');

            Route::get(
                '/cycles/{cycleId}/quarterly-report-files',
                [QuarterlyReportFileController::class, 'byCycle']
            )->name('cycles.quarterly-report-files');

            Route::get(
                '/files/{fileId}/quarterly-report-files',
                [QuarterlyReportFileController::class, 'byFile']
            )->name('files.quarterly-report-files');

            Route::get(
                '/users/{userId}/quarterly-report-files',
                [QuarterlyReportFileController::class, 'byGenerator']
            )->name('users.quarterly-report-files');

            Route::get(
                '/cycles/{cycleId}/quarterly-report-files',
                [QuarterlyReportFileController::class, 'byCycle']
            )->name('cycles.quarterly-report-files');

            Route::get(
                '/files/{fileId}/quarterly-report-files',
                [QuarterlyReportFileController::class, 'byFile']
            )->name('files.quarterly-report-files');

            Route::get(
                '/users/{userId}/quarterly-report-files',
                [QuarterlyReportFileController::class, 'byGenerator']
            )->name('users.quarterly-report-files');

            Route::post(
                '/report-share-attempts',
                [ReportShareAttemptController::class, 'store']
            )->name('report-share-attempts.store');

            Route::get(
                '/report-share-attempts/{id}',
                [ReportShareAttemptController::class, 'show']
            )->name('report-share-attempts.show');

            Route::get(
                '/quarterly-report-files/{quarterlyReportFileId}/share-attempts',
                [ReportShareAttemptController::class, 'byQuarterlyReportFile']
            )->name('quarterly-report-files.share-attempts');

            Route::get(
                '/teachers/{teacherId}/report-share-attempts',
                [ReportShareAttemptController::class, 'byTeacher']
            )->name('teachers.report-share-attempts');

            Route::get(
                '/guardians/{guardianId}/report-share-attempts',
                [ReportShareAttemptController::class, 'byGuardian']
            )->name('guardians.report-share-attempts');

            Route::post(
                '/monthly-report-skills',
                [MonthlyReportSkillController::class, 'store']
            )->name('monthly-report-skills.store');

            Route::get(
                '/monthly-report-skills/{reportId}/{skillId}',
                [MonthlyReportSkillController::class, 'show']
            )->name('monthly-report-skills.show');

            Route::get(
                '/monthly-reports/{reportId}/skills',
                [MonthlyReportSkillController::class, 'byReport']
            )->name('monthly-reports.skills');

            Route::get(
                '/learning-skills/{skillId}/monthly-reports',
                [MonthlyReportSkillController::class, 'bySkill']
            )->name('learning-skills.monthly-reports');

            Route::put(
                '/monthly-report-skills/{reportId}/{skillId}',
                [MonthlyReportSkillController::class, 'update']
            )->name('monthly-report-skills.update');

            Route::delete(
                '/monthly-report-skills/{reportId}/{skillId}',
                [MonthlyReportSkillController::class, 'destroy']
            )->name('monthly-report-skills.destroy');

            Route::post(
                '/notifications',
                [NotificationController::class, 'store']
            )->name('notifications.store');

            Route::get(
                '/notifications/{id}',
                [NotificationController::class, 'show']
            )->name('notifications.show');

            Route::get(
                '/users/{userId}/notifications',
                [NotificationController::class, 'byUser']
            )->name('users.notifications');

            Route::put(
                '/notifications/{id}',
                [NotificationController::class, 'update']
            )->name('notifications.update');

            Route::delete(
                '/notifications/{id}',
                [NotificationController::class, 'destroy']
            )->name('notifications.destroy');

            Route::get(
                '/public-content-sections',
                [PublicContentSectionController::class, 'index']
            )->name('public-content-sections.index');

            Route::post(
                '/public-content-sections',
                [PublicContentSectionController::class, 'store']
            )->name('public-content-sections.store');

            Route::get(
                '/public-content-sections/published',
                [PublicContentSectionController::class, 'published']
            )->name('public-content-sections.published');

            Route::get(
                '/public-content-sections/{id}',
                [PublicContentSectionController::class, 'show']
            )->name('public-content-sections.show');

            Route::get(
                '/file-assets/{imageFileId}/public-content-sections',
                [PublicContentSectionController::class, 'byImageFile']
            )->name('file-assets.public-content-sections');

            Route::put(
                '/public-content-sections/{id}',
                [PublicContentSectionController::class, 'update']
            )->name('public-content-sections.update');

            Route::delete(
                '/public-content-sections/{id}',
                [PublicContentSectionController::class, 'destroy']
            )->name('public-content-sections.destroy');

            Route::post('/report-status-events', [ReportStatusEventController::class, 'store'])
                ->name('report-status-events.store');

            Route::get('/report-status-events/{id}', [ReportStatusEventController::class, 'show'])
                ->name('report-status-events.show');

            Route::get('/monthly-reports/{reportId}/status-events', [ReportStatusEventController::class, 'byReport'])
                ->name('monthly-reports.status-events');

            Route::get('/users/{userId}/report-status-events', [ReportStatusEventController::class, 'byActor'])
                ->name('users.report-status-events');

            Route::put('/report-status-events/{id}', [ReportStatusEventController::class, 'update'])
                ->name('report-status-events.update');

            Route::delete('/report-status-events/{id}', [ReportStatusEventController::class, 'destroy'])
                ->name('report-status-events.destroy');

            Route::post('/session-students', [SessionStudentController::class, 'store'])
                ->name('session-students.store');

            Route::get('/session-students/{sessionId}/{studentId}', [SessionStudentController::class, 'show'])
                ->name('session-students.show');

            Route::get('/lesson-sessions/{sessionId}/students', [SessionStudentController::class, 'bySession'])
                ->name('lesson-sessions.students');

            Route::get('/students/{studentId}/lesson-sessions', [SessionStudentController::class, 'byStudent'])
                ->name('students.lesson-sessions');

            Route::put('/session-students/{sessionId}/{studentId}', [SessionStudentController::class, 'update'])
                ->name('session-students.update');

            Route::delete('/session-students/{sessionId}/{studentId}', [SessionStudentController::class, 'destroy'])
                ->name('session-students.destroy');

            Route::get(
                '/system-settings',
                [SystemSettingController::class, 'show']
            )->name('system-settings.show');

            Route::put(
                '/system-settings',
                [SystemSettingController::class, 'update']
            )->name('system-settings.update');
        });

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin,superadmin')
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
        ->middleware('role:teacher,superadmin')
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
        ->middleware('role:student,superadmin')
        ->group(function () {

            Route::get('/dashboard', [
                StudentDashboardController::class,
                'index',
            ])->name('dashboard');
        });
});
