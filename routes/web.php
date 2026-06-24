<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\TutorController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\ClassSessionController;
use App\Http\Controllers\Tutor\DashboardController as TutorDashboard;
use App\Http\Controllers\Tutor\AttendanceController as TutorAttendance;
use App\Http\Controllers\Tutor\AvailabilityController as TutorAvailability;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\FixedAssetController;
use App\Http\Controllers\Tutor\ScheduleController as TutorSchedule;
use App\Http\Controllers\Admin\ScheduleController as AdminSchedule;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendance;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TrackerController;
use App\Http\Controllers\Admin\AdjustingJournalController;
use App\Http\Controllers\Admin\RabController;
use App\Http\Controllers\Admin\RabRealisasiController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Global search — admin & CFO only (topbar widget). Returns JSON.
    // Throttled to 30 req/min per user to prevent abuse / scraping.
    Route::get('/search', SearchController::class)
        ->middleware('role:admin,cfo')
        ->middleware('throttle:30,1')
        ->name('search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin routes — operasional
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function() {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

        Route::get('/students/data', [StudentController::class, 'data'])->name('students.data');
        Route::resource('students', StudentController::class)->except(['create', 'store']);
        Route::resource('programs', ProgramController::class);
        Route::get('enrollments/students/search', [EnrollmentController::class, 'searchStudents'])->name('enrollments.students.search');
        Route::get('enrollments/sessions/eligible', [EnrollmentController::class, 'eligibleSessions'])->name('enrollments.sessions.eligible');
        Route::get('enrollments/tutors/available', [EnrollmentController::class, 'availableTutors'])->name('enrollments.tutors.available');
        Route::get('/enrollments/data', [EnrollmentController::class, 'data'])->name('enrollments.data');
        Route::resource('enrollments', EnrollmentController::class);
        Route::resource('classrooms', ClassroomController::class);
        Route::resource('tutors', TutorController::class);
        Route::get('/class-sessions/enrollments/{programId}', [ClassSessionController::class, 'availableEnrollments'])->name('class-sessions.available-enrollments');
        Route::resource('class-sessions', ClassSessionController::class);
        Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/users', [App\Http\Controllers\Admin\SettingsController::class, 'storeUser'])->name('settings.users.store');
        Route::patch('/settings/users/{user}', [App\Http\Controllers\Admin\SettingsController::class, 'updateUser'])->name('settings.users.update');
        Route::delete('/settings/users/{user}', [App\Http\Controllers\Admin\SettingsController::class, 'destroyUser'])->name('settings.users.destroy');
        Route::get('/settings/colors', [App\Http\Controllers\Admin\SettingsController::class, 'colors'])->name('settings.colors');
        Route::post('/settings/colors', [App\Http\Controllers\Admin\SettingsController::class, 'updateColors'])->name('settings.colors.update');

        Route::post('/enrollments/{id}/expire', [EnrollmentController::class, 'expire'])->name('enrollments.expire');
        Route::post('/enrollments/{id}/graduate', [EnrollmentController::class, 'graduate'])->name('enrollments.graduate');
        Route::post('/enrollments/{id}/assign-tutor', [EnrollmentController::class, 'assignTutor'])->name('enrollments.assign-tutor');
        Route::post('/enrollments/{id}/remove-tutor', [EnrollmentController::class, 'removeTutor'])->name('enrollments.remove-tutor');
        Route::patch('/enrollments/{id}/tutor/{tutorId}/status', [EnrollmentController::class, 'updateTutorStatus'])->name('enrollments.tutor-status');

        Route::post('/tutors/{id}/confirm-enrollment', [TutorController::class, 'confirmEnrollment'])->name('tutors.confirm-enrollment');
        Route::post('/tutors/{id}/rates', [TutorController::class, 'storeRate'])->name('tutors.rates.store');
        Route::post('/tutors/{id}/availability', [TutorController::class, 'storeAvailability'])->name('tutors.availability.store');
        Route::post('/tutors/{id}/availability/custom', [TutorController::class, 'storeCustomAvailability'])->name('tutors.availability.custom');
        Route::delete('/tutors/{id}/availability/{availabilityId}', [TutorController::class, 'destroyAvailability'])->name('tutors.availability.destroy');
        Route::post('room-bookings', [App\Http\Controllers\Admin\RoomBookingController::class, 'store'])->name('room-bookings.store');
        Route::delete('room-bookings/{id}', [App\Http\Controllers\Admin\RoomBookingController::class, 'destroy'])->name('room-bookings.destroy');

        Route::post('/enrollments/{enrollmentId}/installments/{installmentId}/paid', [EnrollmentController::class, 'markInstallmentPaid'])->name('enrollments.installments.paid')->middleware('idempotent');

        Route::post('/class-sessions/{id}/assign', [ClassSessionController::class, 'assignEnrollment'])->name('class-sessions.assign');
        Route::post('/class-sessions/{id}/remove', [ClassSessionController::class, 'removeEnrollment'])->name('class-sessions.remove');
        Route::post('/class-sessions/{id}/assign-tutor', [ClassSessionController::class, 'assignTutor'])->name('class-sessions.assign-tutor');
        Route::post('/class-sessions/{id}/remove-tutor', [ClassSessionController::class, 'removeTutor'])->name('class-sessions.remove-tutor');
        Route::patch('/class-sessions/{id}/tutor/{tutorId}/status', [ClassSessionController::class, 'updateTutorStatus'])->name('class-sessions.tutor-status');
        Route::post('/class-sessions/{id}/schedules', [ClassSessionController::class, 'storeSchedule'])->name('class-sessions.schedules.store');
        Route::delete('/class-sessions/{id}/schedules/{scheduleId}', [ClassSessionController::class, 'destroySchedule'])->name('class-sessions.schedules.destroy');
        Route::get('/class-sessions/{id}/info', [ClassSessionController::class, 'info'])->name('class-sessions.info');

        // Imports — operasional
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports/classrooms', [ImportController::class, 'importClassrooms'])->name('imports.classrooms');
        Route::post('/imports/programs', [ImportController::class, 'importPrograms'])->name('imports.programs');
        Route::post('/imports/tutors', [ImportController::class, 'importTutors'])->name('imports.tutors');
        Route::post('/imports/students', [ImportController::class, 'importStudents'])->name('imports.students');
        Route::post('/imports/enrollments', [ImportController::class, 'importEnrollments'])->name('imports.enrollments');
        Route::post('/imports/installments', [ImportController::class, 'importInstallments'])->name('imports.installments');
        Route::post('/imports/schedules', [ImportController::class, 'importSchedules'])->name('imports.schedules');
        Route::post('/imports/tutor-availability', [ImportController::class, 'importTutorAvailability'])->name('imports.tutor-availability');
        Route::post('/imports/class-sessions', [ImportController::class, 'importClassSessions'])->name('imports.class-sessions');
        Route::post('/imports/rabs', [ImportController::class, 'importRabs'])->name('imports.rabs');
        Route::post('/imports/fixed-assets', [ImportController::class, 'importFixedAssets'])->name('imports.fixed-assets');
        Route::post('/imports/tracker-columns', [ImportController::class, 'importTrackerColumns'])->name('imports.tracker-columns');

        // Exports — operasional
        Route::get('/exports/attendance', [ExportController::class, 'exportAttendance'])->name('exports.attendance');
        Route::get('/exports/template/{type}', [ExportController::class, 'downloadTemplate'])->name('exports.template');

        Route::get('/schedule', [AdminSchedule::class, 'index'])->name('schedule.index');
        Route::patch('/schedules/{id}', [AdminSchedule::class, 'update'])->name('schedule.update');
        Route::delete('/schedules/{id}', [AdminSchedule::class, 'destroy'])->name('schedule.destroy');
        Route::post('/schedules', [AdminSchedule::class, 'store'])->name('schedule.store');
        Route::get('/attendance/data', [AdminAttendance::class, 'data'])->name('attendance.data');
        Route::get('/attendance', [AdminAttendance::class, 'index'])->name('attendance.index');
        Route::delete('/attendance/{id}', [AdminAttendance::class, 'destroy'])->name('attendance.destroy');
        Route::patch('/attendance/{id}', [AdminAttendance::class, 'update'])->name('attendance.update');
        Route::get('/tracker', [App\Http\Controllers\Admin\TrackerController::class, 'index'])->name('tracker.index');
        Route::post('/tracker/columns', [App\Http\Controllers\Admin\TrackerController::class, 'storeColumn'])->name('tracker.columns.store');
        Route::delete('/tracker/columns/{column}', [App\Http\Controllers\Admin\TrackerController::class, 'destroyColumn'])->name('tracker.columns.destroy');
        Route::post('/tracker/toggle', [App\Http\Controllers\Admin\TrackerController::class, 'toggle'])->name('tracker.toggle');
    });

    // CFO routes — finance
    Route::prefix('finance')->name('finance.')->middleware('role:cfo')->group(function() {
        Route::get('/', [FinanceController::class, 'dashboard'])->name('index');
        Route::get('/reports', [FinanceController::class, 'reports'])->name('reports');
        Route::get('/chart/revenue-by-program', [FinanceController::class, 'chartRevenueByProgram'])->name('chart.revenue-by-program');
        Route::post('/pending-rates/{id}/assign', [FinanceController::class, 'assignRate'])->name('rate.assign');

        Route::resource('accounts', AccountController::class);
        Route::get('/journals/data', [JournalController::class, 'data'])->name('journals.data');
        Route::resource('journals', JournalController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse')->middleware('idempotent');


        Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('/reports/adjusted-trial-balance', [ReportController::class, 'adjustedTrialBalance'])->name('reports.adjusted-trial-balance');
        Route::get('/reports/general-ledger', [ReportController::class, 'generalLedger'])->name('reports.general-ledger');
        Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
        Route::get('/assets', [FixedAssetController::class, 'index'])->name('assets.index');
        Route::post('/assets', [FixedAssetController::class, 'store'])->name('assets.store');
        Route::patch('/assets/{fixedAsset}', [FixedAssetController::class, 'update'])->name('assets.update');
        Route::post('/assets/generate-depreciation', [FixedAssetController::class, 'generateDepreciation'])->name('assets.generate-depreciation');
        Route::delete('/assets/{fixedAsset}', [FixedAssetController::class, 'destroy'])->name('assets.destroy');
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
        Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('/reports/equity-statement', [App\Http\Controllers\Admin\EquityStatementController::class, 'index'])->name('reports.equity-statement');
        Route::post('/reports/opening-balance', [ReportController::class, 'storeOpeningBalance'])->name('opening-balance.store');
        Route::get('/reports/deferred-revenue', [ReportController::class, 'deferredRevenue'])->name('reports.deferred-revenue');

        // Adjusting Journals
        Route::get('/adjusting-journals', [AdjustingJournalController::class, 'index'])->name('adjusting-journals.index');
        Route::get('/adjusting-journals/data', [AdjustingJournalController::class, 'data'])->name('adjusting-journals.data');
        Route::post('/adjusting-journals', [AdjustingJournalController::class, 'store'])->name('adjusting-journals.store');
        Route::post('/adjusting-journals/generate', [AdjustingJournalController::class, 'generate'])->name('adjusting-journals.generate');
        Route::delete('/adjusting-journals/{adjustingJournal}', [AdjustingJournalController::class, 'destroy'])->name('adjusting-journals.destroy');

        // Exports — finance
        Route::get('/exports/journals', [ExportController::class, 'exportJournals'])->name('exports.journals');
        Route::get('/exports/payroll', [ExportController::class, 'exportPayroll'])->name('exports.payroll');
        Route::get('/exports/trial-balance', [ExportController::class, 'exportTrialBalance'])->name('exports.trial-balance');
        Route::get('/exports/profit-loss', [ExportController::class, 'exportProfitLoss'])->name('exports.profit-loss');
        Route::get('/exports/balance-sheet', [ExportController::class, 'exportBalanceSheet'])->name('exports.balance-sheet');
        Route::get('/exports/coa', [ExportController::class, 'exportCoA'])->name('exports.coa');
        Route::get('/exports/deferred-revenue', [ExportController::class, 'exportDeferredRevenue'])->name('exports.deferred-revenue');
        Route::get('/exports/finance-template/{type}', [ExportController::class, 'downloadTemplate'])->name('exports.finance-template');

        // Imports — finance
        Route::post('/imports/coa', [ImportController::class, 'importCOA'])->name('imports.coa');
        Route::post('/imports/journals', [ImportController::class, 'importJournals'])->name('imports.journals');
        Route::get('/imports', [ImportController::class, 'financeImports'])->name('imports');

        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store')->middleware('idempotent');
        Route::post('/payroll/{id}/approve', [PayrollController::class, 'approve'])->name('payroll.approve')->middleware('idempotent');
        Route::post('/payroll/{id}/reverse', [PayrollController::class, 'reverse'])->name('payroll.reverse')->middleware('idempotent');

        // RAB
        Route::get('/rab', [RabController::class, 'index'])->name('rab.index');
        Route::post('/rab', [RabController::class, 'store'])->name('rab.store');
        Route::get('/rab/data', [RabController::class, 'data'])->name('rab.data');
        Route::delete('/rab/{rab}', [RabController::class, 'destroy'])->name('rab.destroy');

        // RAB Realisasi
        Route::get('/rab-realisasi', [RabRealisasiController::class, 'index'])->name('rab-realisasi.index');
    });

    // Tutor routes
    Route::prefix('tutor')->name('tutor.')->middleware('role:tutor')->group(function() {
        Route::get('/dashboard', [TutorDashboard::class, 'index'])->name('dashboard');
        Route::get('/attendance/data', [TutorAttendance::class, 'data'])->name('attendance.data');
        Route::get('/attendance', [TutorAttendance::class, 'index'])->name('attendance.index');
        Route::get('/attendance/search-sessions', [TutorAttendance::class, 'searchSessions'])->name('attendance.search-sessions');
        Route::get('/attendance/history', [TutorAttendance::class, 'history'])->name('attendance.history');
        Route::get('/schedule', [TutorSchedule::class, 'index'])->name('schedule.index');
        Route::post('/attendance', [TutorAttendance::class, 'store'])->name('attendance.store');
        Route::delete('/attendance/{id}', [TutorAttendance::class, 'destroy'])->name('attendance.destroy');
        Route::get('/availability', [TutorAvailability::class, 'index'])->name('availability.index');
        Route::post('/availability', [TutorAvailability::class, 'store'])->name('availability.store');
        Route::delete('/availability/{id}', [TutorAvailability::class, 'destroy'])->name('availability.destroy');
        Route::patch('/availability/{id}', [TutorAvailability::class, 'update'])->name('availability.update');
        Route::post('/room-bookings', [App\Http\Controllers\Tutor\RoomBookingController::class, 'store'])->name('room-bookings.store');
        Route::delete('/room-bookings/{id}', [App\Http\Controllers\Tutor\RoomBookingController::class, 'destroy'])->name('room-bookings.destroy');
        Route::get('practice', [App\Http\Controllers\Tutor\PracticeController::class, 'index'])->name('practice.index');
        Route::get('practice/create', [App\Http\Controllers\Tutor\PracticeController::class, 'create'])->name('practice.create');
        Route::post('practice', [App\Http\Controllers\Tutor\PracticeController::class, 'store'])->name('practice.store');
        Route::get('practice/{practice}/edit', [App\Http\Controllers\Tutor\PracticeController::class, 'edit'])->name('practice.edit');
        Route::put('practice/{practice}', [App\Http\Controllers\Tutor\PracticeController::class, 'update'])->name('practice.update');
        Route::get('tracker', [App\Http\Controllers\Tutor\TrackerController::class, 'index'])->name('tracker.index');
    });

    // Student routes
    Route::prefix('student')->name('student.')->middleware('role:student')->group(function() {
        Route::get('/dashboard', [StudentDashboard::class, 'index'])->name('dashboard');
        Route::get('/practice', [App\Http\Controllers\Student\PracticeController::class, 'index'])->name('practice.index');
        Route::post('/practice/{practice}/open', [App\Http\Controllers\Student\PracticeController::class, 'open'])->name('practice.open');
        Route::post('/practice/{practice}/submit', [App\Http\Controllers\Student\PracticeController::class, 'submit'])->name('practice.submit');
        Route::get('/tracker', [App\Http\Controllers\Student\TrackerController::class, 'index'])->name('tracker.index');
    });
});

require __DIR__.'/auth.php';
