<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MonthlyReportController as AdminMonthlyReportController;
use App\Http\Controllers\Admin\ReportCycleController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherAttendanceController as AdminTeacherAttendanceController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InAppNotificationController;
use App\Http\Controllers\OperationsMonitoringController;
use App\Http\Controllers\OperationalExportController;
use App\Http\Controllers\LearningSkillController;
use App\Http\Controllers\LearningVideoController;
use App\Http\Controllers\LessonLearningController;
use App\Http\Controllers\MonthlyChargeController;
use App\Http\Controllers\MonthlyReportPageController;
use App\Http\Controllers\MonthlyReportSkillController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaymentRecapRunController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PublicImageController;
use App\Http\Controllers\PublicContentSectionController;
use App\Http\Controllers\QuarterlyReportFileController;
use App\Http\Controllers\QuarterlyReportPageController;
use App\Http\Controllers\ReportShareAttemptController;
use App\Http\Controllers\ReportStatusEventController;
use App\Http\Controllers\SessionStudentController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\PortalController as StudentPortalController;
use App\Http\Controllers\StudentAttendanceController;
use App\Http\Controllers\StudentChallengeController;
use App\Http\Controllers\StudentProgressController;
use App\Http\Controllers\SuperAdmin\BranchAdminAssignmentController;
use App\Http\Controllers\SuperAdmin\BranchController;
use App\Http\Controllers\SuperAdmin\ClassGroupController;
use App\Http\Controllers\SuperAdmin\ClassGroupMembershipController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\GuardianController;
use App\Http\Controllers\SuperAdmin\LessonSessionController;
use App\Http\Controllers\SuperAdmin\PaymentReviewController;
use App\Http\Controllers\SuperAdmin\ProgramController;
use App\Http\Controllers\SuperAdmin\StudentController as SuperAdminStudentController;
use App\Http\Controllers\SuperAdmin\StudentEnrollmentController;
use App\Http\Controllers\SuperAdmin\StudentTeacherAssignmentController;
use App\Http\Controllers\SuperAdmin\TeacherAvailabilityController;
use App\Http\Controllers\SuperAdmin\TeacherBranchAssignmentController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\MonthlyReportController as TeacherMonthlyReportController;
use App\Http\Controllers\TeacherMessageController;
use App\Http\Middleware\PrivatePortalResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/tentang', [HomeController::class, 'about'])->name('public.about');
Route::get('/program', [HomeController::class, 'programs'])->name('public.programs');
Route::get('/program/{id}', [HomeController::class, 'program'])->name('public.program');
Route::get('/cabang', [HomeController::class, 'branches'])->name('public.branches');
Route::get('/kontak', [HomeController::class, 'contact'])->name('public.contact');
Route::get('/gambar-publik/{id}', PublicImageController::class)->whereUuid('id')->name('public.image');

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
    ->middleware(['auth', 'auth.session'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'auth.session'])->group(function () {

    foreach (['teacher', 'student'] as $accountRole) {
        Route::prefix($accountRole)->name($accountRole.'.')->middleware(['role:'.$accountRole, PrivatePortalResponse::class])->group(function () {
            Route::get('/settings', [\App\Http\Controllers\AccountSettingsController::class, 'index'])->name('settings.index');
            Route::put('/settings/password', [\App\Http\Controllers\AccountSettingsController::class, 'password'])
                ->middleware('throttle:6,1,account-password')->name('settings.password');
        });
    }
    Route::prefix('teacher/profile')->name('teacher.profile.')->middleware(['role:teacher', PrivatePortalResponse::class])->group(function () {
        Route::get('/', [\App\Http\Controllers\Teacher\ProfileController::class, 'show'])->name('show');
        Route::get('/edit', [\App\Http\Controllers\Teacher\ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [\App\Http\Controllers\Teacher\ProfileController::class, 'update'])->middleware('throttle:20,1,own-profile')->name('update');
        Route::get('/photo', [\App\Http\Controllers\Teacher\ProfileController::class, 'photo'])->name('photo');
        Route::get('/signature', [\App\Http\Controllers\ReportSignatureController::class, 'teacherImage'])->name('signature.image');
        Route::post('/signature', [\App\Http\Controllers\ReportSignatureController::class, 'teacherStore'])->middleware('throttle:10,1,report-signature')->name('signature.store');
    });

    Route::prefix('admin')->name('admin.')->middleware(['role:admin,superadmin', PrivatePortalResponse::class])->group(function () {
        Route::resource('users', \App\Http\Controllers\Admin\StudentUserController::class)
            ->only(['index', 'create', 'store', 'show'])->middleware('role:admin');
        Route::get('/branch-settings', [\App\Http\Controllers\Admin\BranchSettingsController::class, 'index'])->name('branch-settings.index');
        Route::get('/branch-settings/{branch}/edit', [\App\Http\Controllers\Admin\BranchSettingsController::class, 'edit'])->name('branch-settings.edit');
        Route::put('/branch-settings/{branch}', [\App\Http\Controllers\Admin\BranchSettingsController::class, 'update'])->name('branch-settings.update');
        Route::get('/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export/{format}', [\App\Http\Controllers\Admin\PaymentController::class, 'download'])->whereIn('format', ['xlsx', 'pdf'])->name('payments.download');
        Route::get('/payments/proofs/{paymentProof}/image', [\App\Http\Controllers\Admin\PaymentController::class, 'image'])->name('payments.image');
        Route::get('/payments/{monthlyCharge}', [\App\Http\Controllers\Admin\PaymentController::class, 'show'])->name('payments.show');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\StudentUserController::class, 'destroy'])->name('users.destroy');
        Route::get('/guardians/{guardian}/edit', [\App\Http\Controllers\Admin\GuardianController::class, 'edit'])
            ->name('guardians.edit');
        Route::patch('/guardians/{guardian}', [\App\Http\Controllers\Admin\GuardianController::class, 'update'])
            ->name('guardians.update');
        Route::get('/teacher-slots', [\App\Http\Controllers\TeacherSlotController::class, 'index'])->name('teacher-slots.index');
        Route::patch('/teacher-slots/{classLearningSession}', [\App\Http\Controllers\TeacherSlotController::class, 'update'])->name('teacher-slots.update');
        Route::resource('class-groups', \App\Http\Controllers\Admin\ClassGroupController::class);
        Route::resource('class-group-memberships', \App\Http\Controllers\Admin\ClassGroupMembershipController::class)->except(['destroy']);
        Route::resource('lesson-sessions', \App\Http\Controllers\Admin\LessonSessionController::class)->except(['destroy']);
    });

    foreach (['admin' => 'admin,superadmin', 'superadmin' => 'superadmin'] as $schedulePrefix => $scheduleRoles) {
        Route::prefix($schedulePrefix.'/lesson-series')->name($schedulePrefix.'.lesson-series.')
            ->middleware(['role:'.$scheduleRoles, PrivatePortalResponse::class])->group(function () {
                Route::get('/create', [\App\Http\Controllers\LessonSeriesController::class, 'create'])->name('create');
                Route::post('/preview', [\App\Http\Controllers\LessonSeriesController::class, 'preview'])->name('preview');
                Route::post('/cancel', [\App\Http\Controllers\LessonSeriesController::class, 'cancel'])->name('cancel');
                Route::post('/', [\App\Http\Controllers\LessonSeriesController::class, 'store'])->name('store');
                Route::get('/{series}', [\App\Http\Controllers\LessonSeriesController::class, 'show'])->name('show');
                Route::delete('/{series}', [\App\Http\Controllers\LessonSeriesController::class, 'destroy'])->name('destroy');
                Route::delete('/{series}/meetings/{lessonSession}', [\App\Http\Controllers\LessonSeriesController::class, 'destroyMeeting'])->name('destroy-meeting');
                Route::get('/{series}/from/{lessonSession}/edit', [\App\Http\Controllers\LessonSeriesController::class, 'edit'])->name('edit');
                Route::post('/{series}/from/{lessonSession}/preview', [\App\Http\Controllers\LessonSeriesController::class, 'previewFuture'])->name('preview-future');
                Route::post('/{series}/from/{lessonSession}', [\App\Http\Controllers\LessonSeriesController::class, 'updateFuture'])->name('update-future');
            });
    }

    Route::middleware(['role:superadmin', PrivatePortalResponse::class])->prefix('superadmin/settings')->name('superadmin.settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SuperAdmin\SystemSettingsPageController::class, 'index'])->name('index');
        Route::put('/', [\App\Http\Controllers\SuperAdmin\SystemSettingsPageController::class, 'update'])->name('update');
    });

    Route::middleware(['role:superadmin,admin,teacher,student', PrivatePortalResponse::class])->prefix('inbox')->name('inbox.')->group(function () {
        Route::get('/', [InAppNotificationController::class, 'index'])->name('index');
        Route::get('/attendance/{teacherAttendance}', [InAppNotificationController::class, 'attendance'])->middleware('role:teacher')->name('attendance');
        Route::post('/read-page', [InAppNotificationController::class, 'readPage'])->middleware('throttle:30,1')->name('read-page');
        Route::post('/{key}/read', [InAppNotificationController::class, 'read'])->where('key', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('read');
        Route::post('/{key}/open', [InAppNotificationController::class, 'open'])->where('key', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('open');
    });
    Route::get('/monitoring', [OperationsMonitoringController::class, 'index'])
        ->middleware(['role:superadmin,admin,teacher', PrivatePortalResponse::class])->name('monitoring.index');
    Route::middleware(['role:superadmin,admin,teacher', PrivatePortalResponse::class])->prefix('exports')->name('exports.')->group(function () {
        Route::get('/', [OperationalExportController::class, 'index'])->name('index');
        Route::get('/download/{format}', [OperationalExportController::class, 'download'])->where('format', 'xlsx|pdf')
            ->middleware('throttle:10,1')->name('download');
    });

    Route::middleware(['role:superadmin', PrivatePortalResponse::class])->prefix('superadmin/website')->name('superadmin.website.')->group(function () {
        $controller = \App\Http\Controllers\SuperAdmin\WebsiteContentController::class;
        Route::get('/', [$controller, 'index'])->name('index');
        Route::get('/create', [$controller, 'create'])->name('create');
        Route::get('/images/{file}', [$controller, 'image'])->whereUuid('file')->name('image');
        Route::get('/{key}/edit', [$controller, 'edit'])->where('key', '[A-Za-z0-9._-]+')->name('edit');
        Route::get('/{key}/preview', [$controller, 'preview'])->where('key', '[A-Za-z0-9._-]+')->name('preview');
        Route::put('/{key}/draft', [$controller, 'save'])->where('key', '[A-Za-z0-9._-]+')->middleware('throttle:30,1')->name('save');
        Route::post('/{key}/publish', [$controller, 'publish'])->where('key', '[A-Za-z0-9._-]+')->middleware('throttle:30,1')->name('publish');
        Route::delete('/{key}/draft', [$controller, 'discard'])->where('key', '[A-Za-z0-9._-]+')->middleware('throttle:30,1')->name('discard');
        Route::post('/{key}/hide', [$controller, 'hide'])->where('key', '[A-Za-z0-9._-]+')->middleware('throttle:30,1')->name('hide');
        Route::delete('/{key}', [$controller, 'remove'])->where('key', '[A-Za-z0-9._-]+')->middleware('throttle:30,1')->name('remove');
    });

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

            Route::get('/student-attendance', [StudentAttendanceController::class, 'index'])->name('student-attendance.index');
            Route::get('/student-progress', [StudentProgressController::class, 'index'])->name('student-progress.index');
            Route::get('/student-progress/students/{student}/prepare', [StudentProgressController::class, 'preparation'])->name('student-progress.preparation');
            Route::post('/student-progress/students/{student}/prepare', [StudentProgressController::class, 'prepare'])->name('student-progress.prepare');
            Route::get('/student-progress/{monthlyReport}', [StudentProgressController::class, 'edit'])->name('student-progress.edit');
            Route::put('/student-progress/{monthlyReport}', [StudentProgressController::class, 'save'])->name('student-progress.save');
            Route::put('/student-progress/{monthlyReport}/target', [StudentProgressController::class, 'target'])->name('student-progress.target');
            Route::get('/student-progress/{monthlyReport}/videos/create', [StudentProgressController::class, 'videoCreate'])->name('student-progress.videos.create');
            Route::post('/student-progress/{monthlyReport}/videos', [StudentProgressController::class, 'videoStore'])->name('student-progress.videos.store');
            Route::get('/student-progress/{monthlyReport}/videos/{learningVideo}/edit', [StudentProgressController::class, 'videoEdit'])->name('student-progress.videos.edit');
            Route::put('/student-progress/{monthlyReport}/videos/{learningVideo}', [StudentProgressController::class, 'videoUpdate'])->name('student-progress.videos.update');
            Route::get('/student-progress/{monthlyReport}/videos/{learningVideo}/remove', [StudentProgressController::class, 'videoConfirm'])->name('student-progress.videos.confirm');
            Route::delete('/student-progress/{monthlyReport}/videos/{learningVideo}', [StudentProgressController::class, 'videoDelete'])->name('student-progress.videos.delete');
            Route::get('/learning-notes', [LessonLearningController::class, 'index'])->name('learning-notes.index');
            Route::get('/learning-notes/{lessonSession}', [LessonLearningController::class, 'edit'])->name('learning-notes.edit');
            Route::put('/learning-notes/{lessonSession}', [LessonLearningController::class, 'save'])->name('learning-notes.save');
            Route::get('/student-attendance/sessions/{lessonSession}', [StudentAttendanceController::class, 'show'])->name('student-attendance.show');
            Route::get('/student-attendance/sessions/{lessonSession}/students/{student}', [StudentAttendanceController::class, 'edit'])->name('student-attendance.edit');
            Route::post('/student-attendance/sessions/{lessonSession}/students/{student}', [StudentAttendanceController::class, 'save'])->name('student-attendance.save');
            Route::put('/student-attendance/sessions/{lessonSession}/participants', [StudentAttendanceController::class, 'roster'])->name('student-attendance.roster');

            Route::resource('users', UserController::class);

            Route::resource('branches', BranchController::class);

            Route::resource(
                'branch-admin-assignments',
                BranchAdminAssignmentController::class
            );

            Route::resource(
                'teacher-branch-assignments',
                TeacherBranchAssignmentController::class
            );

            Route::resource('programs', ProgramController::class);

            Route::resource(
                'student-enrollments',
                StudentEnrollmentController::class
            );

            Route::resource(
                'students',
                SuperAdminStudentController::class
            )->except(['destroy']);

            Route::resource(
                'class-groups',
                ClassGroupController::class
            );

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

            Route::get('/teacher-slots', [\App\Http\Controllers\TeacherSlotController::class, 'index'])->name('teacher-slots.index');
            Route::patch('/teacher-slots/{classLearningSession}', [\App\Http\Controllers\TeacherSlotController::class, 'update'])->name('teacher-slots.update');

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

            Route::get('/student-attendance', [StudentAttendanceController::class, 'index'])->name('student-attendance.index');
            Route::get('/student-progress', [StudentProgressController::class, 'index'])->name('student-progress.index');
            Route::get('/student-progress/students/{student}/prepare', [StudentProgressController::class, 'preparation'])->name('student-progress.preparation');
            Route::post('/student-progress/students/{student}/prepare', [StudentProgressController::class, 'prepare'])->name('student-progress.prepare');
            Route::get('/student-progress/{monthlyReport}', [StudentProgressController::class, 'edit'])->name('student-progress.edit');
            Route::put('/student-progress/{monthlyReport}', [StudentProgressController::class, 'save'])->name('student-progress.save');
            Route::put('/student-progress/{monthlyReport}/target', [StudentProgressController::class, 'target'])->name('student-progress.target');
            Route::get('/student-progress/{monthlyReport}/videos/create', [StudentProgressController::class, 'videoCreate'])->name('student-progress.videos.create');
            Route::post('/student-progress/{monthlyReport}/videos', [StudentProgressController::class, 'videoStore'])->name('student-progress.videos.store');
            Route::get('/student-progress/{monthlyReport}/videos/{learningVideo}/edit', [StudentProgressController::class, 'videoEdit'])->name('student-progress.videos.edit');
            Route::put('/student-progress/{monthlyReport}/videos/{learningVideo}', [StudentProgressController::class, 'videoUpdate'])->name('student-progress.videos.update');
            Route::get('/student-progress/{monthlyReport}/videos/{learningVideo}/remove', [StudentProgressController::class, 'videoConfirm'])->name('student-progress.videos.confirm');
            Route::delete('/student-progress/{monthlyReport}/videos/{learningVideo}', [StudentProgressController::class, 'videoDelete'])->name('student-progress.videos.delete');
            Route::get('/learning-notes', [LessonLearningController::class, 'index'])->name('learning-notes.index');
            Route::get('/learning-notes/{lessonSession}', [LessonLearningController::class, 'edit'])->name('learning-notes.edit');
            Route::put('/learning-notes/{lessonSession}', [LessonLearningController::class, 'save'])->name('learning-notes.save');
            Route::get('/student-attendance/sessions/{lessonSession}', [StudentAttendanceController::class, 'show'])->name('student-attendance.show');
            Route::get('/student-attendance/sessions/{lessonSession}/students/{student}', [StudentAttendanceController::class, 'edit'])->name('student-attendance.edit');
            Route::post('/student-attendance/sessions/{lessonSession}/students/{student}', [StudentAttendanceController::class, 'save'])->name('student-attendance.save');
            Route::put('/student-attendance/sessions/{lessonSession}/participants', [StudentAttendanceController::class, 'roster'])->name('student-attendance.roster');

            Route::resource('students', StudentController::class)
                ->except(['destroy']);

            Route::resource('teachers', TeacherController::class)
                ->except(['destroy']);

            Route::get('/teacher-attendance', [
                AdminTeacherAttendanceController::class,
                'index',
            ])->name('teacher-attendance.index');

            Route::post('/teacher-attendance/sessions/{lessonSession}/mark', [
                AdminTeacherAttendanceController::class,
                'markException',
            ])->name('teacher-attendance.sessions.mark');

            Route::get('/teacher-attendance/{teacherAttendance}', [
                AdminTeacherAttendanceController::class,
                'show',
            ])->name('teacher-attendance.show');

            Route::patch('/teacher-attendance/{teacherAttendance}/review', [
                AdminTeacherAttendanceController::class,
                'review',
            ])->name('teacher-attendance.review');

            Route::get('/teacher-attendance/{teacherAttendance}/photo/{side}', [
                AdminTeacherAttendanceController::class,
                'photo',
            ])->where('side', 'check-in|check-out')
                ->name('teacher-attendance.photo');

            Route::get('/teacher-attendance/{teacherAttendance}/location/{side}', [
                AdminTeacherAttendanceController::class,
                'location',
            ])->where('side', 'check-in|check-out')
                ->name('teacher-attendance.location');
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
        ->middleware(['role:admin,superadmin', PrivatePortalResponse::class])
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

    foreach (['teacher' => 'teacher', 'admin' => 'admin,superadmin', 'superadmin' => 'superadmin'] as $role => $allowedRoles) {
        Route::prefix($role.'/monthly-reports')->name($role.'.monthly-reports.')->middleware('role:'.$allowedRoles)
            ->group(function () use ($role) {
                Route::get('/', [MonthlyReportPageController::class, 'index'])->name('index');
                Route::middleware(\App\Http\Middleware\PrivatePortalResponse::class)->group(function () {
                    $wa = \App\Http\Controllers\MonthlyReportWhatsappController::class;
                    Route::get('/{monthlyReport}/pdf', [$wa, 'pdfPreview'])->name('pdf.preview');
                    Route::get('/{monthlyReport}/whatsapp', [$wa, 'index'])->name('whatsapp.index');
                    Route::post('/{monthlyReport}/whatsapp/preview', [$wa, 'preview'])->name('whatsapp.preview');
                    Route::post('/{monthlyReport}/whatsapp', [$wa, 'prepare'])->middleware('throttle:20,1')->name('whatsapp.prepare');
                    Route::get('/{monthlyReport}/whatsapp/{share}', [$wa, 'show'])->whereUuid('share')->name('whatsapp.show');
                    Route::post('/{monthlyReport}/whatsapp/{share}/open', [$wa, 'open'])->whereUuid('share')->middleware('throttle:20,1')->name('whatsapp.open');
                    Route::post('/{monthlyReport}/whatsapp/{share}/download', [$wa, 'download'])->whereUuid('share')->middleware('throttle:20,1')->name('whatsapp.download');
                    Route::post('/{monthlyReport}/whatsapp/{share}/confirm', [$wa, 'confirm'])->whereUuid('share')->middleware('throttle:20,1')->name('whatsapp.confirm');
                });
                Route::get('/{monthlyReport}/confirm/{action}', [MonthlyReportPageController::class, 'confirmation'])
                    ->where('action', 'submit|approve|revision|reopen')->name('confirm');
                Route::get('/{monthlyReport}', [MonthlyReportPageController::class, 'show'])->name('show');
                if ($role === 'teacher') {
                    Route::post('/{monthlyReport}/submit', [MonthlyReportPageController::class, 'submit'])->name('submit');
                    Route::post('/{monthlyReport}/reopen', [MonthlyReportPageController::class, 'reopen'])->name('reopen');
                } else {
                    Route::post('/{monthlyReport}/approve', [MonthlyReportPageController::class, 'approve'])->name('approve');
                    Route::post('/{monthlyReport}/revision', [MonthlyReportPageController::class, 'revision'])->name('revision');
                }
            });
    }

    foreach (['teacher' => 'teacher', 'admin' => 'admin,superadmin', 'superadmin' => 'superadmin'] as $role => $allowedRoles) {
        Route::prefix($role.'/quarterly-reports')->name($role.'.quarterly-reports.')->middleware('role:'.$allowedRoles)
            ->group(function () use ($role) {
                Route::get('/', [QuarterlyReportPageController::class, 'index'])->name('index');
                Route::get('/cycles/{reportCycle}/prepare', [QuarterlyReportPageController::class, 'prepare'])->name('prepare');
                Route::post('/cycles/{reportCycle}', [QuarterlyReportPageController::class, 'store'])->name('store');
                Route::middleware(PrivatePortalResponse::class)->group(function () {
                    $wa = \App\Http\Controllers\ReportWhatsappController::class;
                    Route::get('/{quarterlyReport}/whatsapp', [$wa, 'index'])->name('whatsapp.index');
                    Route::post('/{quarterlyReport}/whatsapp/preview', [$wa, 'preview'])->name('whatsapp.preview');
                    Route::post('/{quarterlyReport}/whatsapp', [$wa, 'prepare'])->middleware('throttle:20,1')->name('whatsapp.prepare');
                    Route::get('/{quarterlyReport}/whatsapp/{share}', [$wa, 'show'])->whereUuid('share')->name('whatsapp.show');
                    Route::post('/{quarterlyReport}/whatsapp/{share}/open', [$wa, 'open'])->whereUuid('share')->middleware('throttle:20,1')->name('whatsapp.open');
                    Route::post('/{quarterlyReport}/whatsapp/{share}/download', [$wa, 'download'])->whereUuid('share')->middleware('throttle:20,1')->name('whatsapp.download');
                    Route::post('/{quarterlyReport}/whatsapp/{share}/confirm', [$wa, 'confirm'])->whereUuid('share')->middleware('throttle:20,1')->name('whatsapp.confirm');
                });
                Route::get('/{quarterlyReport}/edit', [QuarterlyReportPageController::class, 'edit'])->name('edit');
                Route::put('/{quarterlyReport}', [QuarterlyReportPageController::class, 'save'])->name('save');
                Route::get('/{quarterlyReport}/confirm/{action}', [QuarterlyReportPageController::class, 'confirmation'])->where('action', 'submit|approve|revision|reopen|generate|regenerate')->name('confirm');
                Route::get('/{quarterlyReport}/preview', [QuarterlyReportPageController::class, 'preview'])->name('preview');
                Route::get('/{quarterlyReport}/files/{quarterlyReportFile}', [QuarterlyReportPageController::class, 'download'])->name('download');
                Route::post('/{quarterlyReport}/generate', [QuarterlyReportPageController::class, 'generate'])->name('generate');
                Route::post('/{quarterlyReport}/regenerate', [QuarterlyReportPageController::class, 'regenerate'])->name('regenerate');
                Route::get('/{quarterlyReport}', [QuarterlyReportPageController::class, 'show'])->name('show');
                foreach ($role === 'teacher' ? ['submit', 'reopen'] : ['approve', 'revision'] as $action) {
                    Route::post('/{quarterlyReport}/'.$action, [QuarterlyReportPageController::class, $action])->name($action);
                }
            });
    }
    Route::get('/superadmin/report-template', [QuarterlyReportPageController::class, 'template'])->middleware('role:superadmin')->name('superadmin.report-template.edit');
    Route::put('/superadmin/report-template', [QuarterlyReportPageController::class, 'templateSave'])->middleware('role:superadmin')->name('superadmin.report-template.save');
    Route::middleware(['role:superadmin', PrivatePortalResponse::class])->prefix('superadmin/report-template/signature')->name('superadmin.report-template.signature.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReportSignatureController::class, 'managerImage'])->name('image');
        Route::post('/', [\App\Http\Controllers\ReportSignatureController::class, 'managerStore'])->middleware('throttle:10,1,report-signature')->name('store');
    });

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

    Route::prefix('teacher')->name('teacher.')
        ->middleware(['role:teacher', PrivatePortalResponse::class])->group(function () {
            Route::get('/schedule', [\App\Http\Controllers\Teacher\ScheduleController::class, 'index'])->name('schedule.index');
            Route::get('/schedule/{lessonSession}', [\App\Http\Controllers\Teacher\ScheduleController::class, 'show'])->name('schedule.show');
            Route::get('/students', [\App\Http\Controllers\Teacher\StudentController::class, 'index'])->name('students.index');
            Route::get('/students/{student}', [\App\Http\Controllers\Teacher\StudentController::class, 'show'])->name('students.show');
            Route::resource('availability', \App\Http\Controllers\Teacher\AvailabilityController::class)->except('destroy');
        });

    Route::prefix('teacher')
        ->name('teacher.')
        ->middleware('role:teacher,superadmin')
        ->group(function () {

            Route::get('/dashboard', [
                TeacherDashboardController::class,
                'index',
            ])->name('dashboard');

            Route::get('/student-attendance', [StudentAttendanceController::class, 'index'])->name('student-attendance.index');
            Route::get('/student-progress', [StudentProgressController::class, 'index'])->name('student-progress.index');
            Route::get('/student-progress/students/{student}/prepare', [StudentProgressController::class, 'preparation'])->name('student-progress.preparation');
            Route::post('/student-progress/students/{student}/prepare', [StudentProgressController::class, 'prepare'])->name('student-progress.prepare');
            Route::get('/student-progress/{monthlyReport}', [StudentProgressController::class, 'edit'])->name('student-progress.edit');
            Route::put('/student-progress/{monthlyReport}', [StudentProgressController::class, 'save'])->name('student-progress.save');
            Route::put('/student-progress/{monthlyReport}/target', [StudentProgressController::class, 'target'])->name('student-progress.target');
            Route::get('/student-progress/{monthlyReport}/videos/create', [StudentProgressController::class, 'videoCreate'])->name('student-progress.videos.create');
            Route::post('/student-progress/{monthlyReport}/videos', [StudentProgressController::class, 'videoStore'])->name('student-progress.videos.store');
            Route::get('/student-progress/{monthlyReport}/videos/{learningVideo}/edit', [StudentProgressController::class, 'videoEdit'])->name('student-progress.videos.edit');
            Route::put('/student-progress/{monthlyReport}/videos/{learningVideo}', [StudentProgressController::class, 'videoUpdate'])->name('student-progress.videos.update');
            Route::get('/student-progress/{monthlyReport}/videos/{learningVideo}/remove', [StudentProgressController::class, 'videoConfirm'])->name('student-progress.videos.confirm');
            Route::delete('/student-progress/{monthlyReport}/videos/{learningVideo}', [StudentProgressController::class, 'videoDelete'])->name('student-progress.videos.delete');
            Route::get('/learning-notes', [LessonLearningController::class, 'index'])->name('learning-notes.index');
            Route::get('/learning-notes/{lessonSession}', [LessonLearningController::class, 'edit'])->name('learning-notes.edit');
            Route::put('/learning-notes/{lessonSession}', [LessonLearningController::class, 'save'])->name('learning-notes.save');
            Route::get('/student-attendance/sessions/{lessonSession}', [StudentAttendanceController::class, 'show'])->name('student-attendance.show');
            Route::get('/student-attendance/sessions/{lessonSession}/students/{student}', [StudentAttendanceController::class, 'edit'])->name('student-attendance.edit');
            Route::post('/student-attendance/sessions/{lessonSession}/students/{student}', [StudentAttendanceController::class, 'save'])->name('student-attendance.save');

            Route::get('/attendance', [
                TeacherAttendanceController::class,
                'index',
            ])->name('attendance.index');

            Route::post('/attendance/branches/{branch}/arrive', [
                TeacherAttendanceController::class,
                'arrive',
            ])->name('attendance.arrive');

            Route::post('/attendance/visits/{teacherAttendance}/depart', [
                TeacherAttendanceController::class,
                'depart',
            ])->name('attendance.depart');

            Route::post('/attendance/{lessonSession}/check-in', [
                TeacherAttendanceController::class,
                'checkIn',
            ])->name('attendance.check-in');

            Route::post('/attendance/{lessonSession}/check-out', [
                TeacherAttendanceController::class,
                'checkOut',
            ])->name('attendance.check-out');

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

    Route::prefix('superadmin/payment-review')->name('superadmin.payment-review.')
        ->middleware(['role:superadmin', PrivatePortalResponse::class])->group(function () {
            Route::get('/', [PaymentReviewController::class, 'index'])->name('index');
            Route::get('/{paymentProof}', [PaymentReviewController::class, 'show'])->name('show');
            Route::get('/{paymentProof}/image', [PaymentReviewController::class, 'image'])->name('image');
            Route::post('/{paymentProof}/review', [PaymentReviewController::class, 'review'])->name('review');
        });
    Route::get('/superadmin/portal-settings', [PaymentReviewController::class, 'settings'])
        ->middleware(['role:superadmin', PrivatePortalResponse::class])->name('superadmin.portal-settings.index');
    Route::put('/superadmin/portal-settings/{branch}', [PaymentReviewController::class, 'settingSave'])
        ->middleware(['role:superadmin', PrivatePortalResponse::class])->name('superadmin.portal-settings.save');

    Route::prefix('student')
        ->name('student.')
        ->middleware(['role:student', PrivatePortalResponse::class])
        ->group(function () {

            Route::get('/dashboard', [
                StudentDashboardController::class,
                'index',
            ])->name('dashboard');
            Route::get('/profile', [StudentPortalController::class, 'profile'])->name('profile');
            Route::get('/schedule', [StudentPortalController::class, 'schedule'])->name('schedule');
            Route::get('/history', [StudentPortalController::class, 'history'])->name('history');
            Route::get('/attendance', [StudentPortalController::class, 'attendance'])->name('attendance');
            Route::get('/reports', [StudentPortalController::class, 'reports'])->name('reports.index');
            Route::get('/reports/monthly/{monthlyReport}', [StudentPortalController::class, 'monthly'])->name('reports.monthly');
            Route::get('/reports/quarterly/{quarterlyReport}', [StudentPortalController::class, 'quarterly'])->name('reports.quarterly');
            Route::get('/reports/quarterly/{quarterlyReport}/files/{quarterlyReportFile}', [StudentPortalController::class, 'pdf'])->name('reports.pdf');
            Route::get('/payments', [StudentPortalController::class, 'payments'])->name('payments.index');
            Route::get('/payments/{monthlyCharge}', [StudentPortalController::class, 'payment'])->name('payments.show');
            Route::post('/payments/{monthlyCharge}/upload', [StudentPortalController::class, 'upload'])->name('payments.upload');
            Route::get('/payments/{monthlyCharge}/upload-context', [StudentPortalController::class, 'uploadContext'])->name('payments.upload-context');
            Route::get('/payment-proofs/{paymentProof}/image', [StudentPortalController::class, 'image'])->name('payments.image');
        });
});
