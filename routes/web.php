<?php

use App\Http\Controllers\AcademicResultsReportController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AdmissionDocumentController;
use App\Http\Controllers\AdmissionFollowUpController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\FeeHeadController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
// use App\Http\Controllers\FeeSpreadsheetController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\GuardianAccountController;
use App\Http\Controllers\HomeworkController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibraryLoanController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\OrganizationLookupController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicEnquiryController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\StudentIdCardController;
use App\Http\Controllers\StudentNoteController;
use App\Http\Controllers\StudentTransferController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherAssignmentController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\ExamMarkController;
use App\Http\Controllers\ResultController;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SuppliesDashboardController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StudentSupplyController;
use App\Http\Controllers\SupplierController;

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/enquiry', [PublicEnquiryController::class, 'create'])->name('public-enquiry.create');
Route::post('/enquiry', [PublicEnquiryController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('public-enquiry.store');

Route::middleware('can:settings.view')->group(function (): void {
    Route::get('/settings', [SchoolSettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/school', [SchoolSettingsController::class, 'school'])->name('settings.school');
    Route::get('/settings/grading', [SchoolSettingsController::class, 'grading'])->name('settings.grading');
    Route::get('/settings/fees', [SchoolSettingsController::class, 'fees'])->name('settings.fees');
    Route::get('/settings/notifications', [SchoolSettingsController::class, 'notifications'])->name('settings.notifications');
});

Route::put('/settings/school', [SchoolSettingsController::class, 'updateSchool'])->name('settings.school.update');
Route::put('/settings/grading', [SchoolSettingsController::class, 'updateGrading'])->name('settings.grading.update');
Route::put('/settings/fees', [SchoolSettingsController::class, 'updateFees'])->name('settings.fees.update');
Route::put('/settings/notifications', [SchoolSettingsController::class, 'updateNotifications'])->name('settings.notifications.update');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
    Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
    Route::get('/academic-years/{academicYear}', [AcademicYearController::class, 'show'])->name('academic-years.show');
    Route::patch('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
    Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic-years.destroy');
    Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
    Route::post('/academic-years/{academicYear}/complete', [AcademicYearController::class, 'complete'])->name('academic-years.complete');
    Route::post('/academic-years/{academicYear}/lock', [AcademicYearController::class, 'lock'])->name('academic-years.lock');
    Route::post('/academic-years/{academicYear}/terms', [AcademicYearController::class, 'storeTerm'])->name('academic-years.terms.store');
    
    Route::resource('classes', SchoolClassController::class)
        ->parameters(['classes' => 'schoolClass']);
    Route::put('/classes/{schoolClass}/subjects', [SchoolClassController::class, 'syncSubjects'])
        ->name('classes.subjects.sync');
    Route::resource('classes.sections', SectionController::class)
        ->shallow()
        ->except(['index'])
        ->parameters(['classes' => 'schoolClass', 'sections' => 'section']);

    Route::post('/sections/{section}/teacher-assignments', [TeacherAssignmentController::class, 'storeSection'])->name('sections.teacher-assignments.store');
    Route::post('/sections/{section}/subject-assignments', [TeacherAssignmentController::class, 'storeSubject'])->name('sections.subject-assignments.store');
    
    Route::resource('attendance', AttendanceController::class)->except(['update']);
    
    Route::get('/reports/attendance', [AttendanceReportController::class, 'index'])->middleware('can:reports.view')->name('reports.attendance.index');
    Route::get('/reports/attendance/export', [AttendanceReportController::class, 'export'])->middleware('can:reports.export')->name('reports.attendance.export');
    Route::get('/reports/academic', [AcademicResultsReportController::class, 'index'])->middleware('can:results.view')->name('reports.academic.index');
    Route::get('/reports/academic/export', [AcademicResultsReportController::class, 'export'])->middleware('can:results.view')->name('reports.academic.export');
    Route::get('/reports/financial', [FinancialReportController::class, 'index'])->middleware('can:reports.financial.view')->name('reports.financial.index');
    Route::get('/reports/financial/export', [FinancialReportController::class, 'export'])->middleware('can:reports.financial.export')->name('reports.financial.export');
    Route::get('/reports/financial/outstanding', [FinancialReportController::class, 'outstanding'])->middleware('can:reports.financial.view')->name('reports.financial.outstanding');
    Route::get('/reports/financial/outstanding/export', [FinancialReportController::class, 'exportOutstanding'])->middleware('can:reports.financial.export')->name('reports.financial.outstanding.export');
    
    Route::resource('certificates', CertificateController::class)
        ->parameters(['certificates' => 'certificateRequest']);
    Route::post('/certificates/issue', [CertificateController::class, 'issue'])->name('certificates.issue');
    Route::post('/certificates/{certificateRequest}/review', [CertificateController::class, 'review'])->name('certificates.review');
    Route::post('/certificates/{certificateRequest}/cancel', [CertificateController::class, 'cancel'])->name('certificates.cancel');
    Route::post('/certificates/{certificateRequest}/revoke', [CertificateController::class, 'revoke'])->name('certificates.revoke');

    Route::resource('timetable', TimetableController::class)
        ->parameters(['timetable' => 'timetableSlot']);

    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::post('/leave-requests/{leaveRequest}/review', [LeaveRequestController::class, 'review'])->name('leave-requests.review');
    Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::get('/homework', [HomeworkController::class, 'index'])->name('homework.index');
    Route::post('/homework', [HomeworkController::class, 'store'])->name('homework.store');
    Route::put('/homework/{homeworkAssignment}', [HomeworkController::class, 'update'])->name('homework.update');
    Route::post('/homework/{homeworkAssignment}/submissions', [HomeworkController::class, 'submit'])->name('homework.submissions.store');
    Route::patch('/homework/{homeworkAssignment}/submissions/{homeworkSubmission}/grade', [HomeworkController::class, 'grade'])->name('homework.submissions.grade');
    
    Route::resource('exams', ExamController::class);
    Route::post('/exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
    Route::post('/exams/{exam}/reopen', [ExamController::class, 'reopen'])->name('exams.reopen');
    // Mark entry for one exam subject
    Route::get('/exams/{exam}/subjects/{examSubject}/marks', [ExamMarkController::class, 'edit'])->name('exams.marks.edit');
    Route::post('/exams/{exam}/subjects/{examSubject}/marks', [ExamMarkController::class, 'store'])->name('exams.marks.store');
    // Published results (students and parents)
    Route::get('/results', [ResultController::class, 'index'])->name('results.index');
    Route::get('/results/{exam}', [ResultController::class, 'show'])->name('results.show');

    Route::get('/fees', [FeeController::class, 'index'])->name('fees.index');
    Route::resource('fee-heads', FeeHeadController::class)->parameters(['fee-heads' => 'feeHead']);
    Route::resource('fee-structures', FeeStructureController::class)->except('show')->parameters(['fee-structures' => 'feeStructure']);
    Route::get('/invoices/generate', [InvoiceController::class, 'generateForm'])->name('invoices.generate.form');
    Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    // Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
    // Route::post('/exams', [ExamController::class, 'store'])->name('exams.store');
    // Route::post('/exams/{exam}/subjects/{examSubject}/marks', [ExamController::class, 'recordMarks'])->name('exams.marks.store');
    // Route::post('/exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
    // Route::post('/exams/{exam}/reopen', [ExamController::class, 'reopen'])->name('exams.reopen');

    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::resource('library/books', BookController::class)
        ->names('library.books')
        ->parameters(['books' => 'book']);
    Route::post('/library/books/{book}/copies', [BookController::class, 'addCopies'])->name('library.books.copies.store');
    Route::patch('/library/copies/{bookCopy}', [BookCopyController::class, 'update'])->name('library.copies.update');
    Route::delete('/library/copies/{bookCopy}', [BookCopyController::class, 'destroy'])->name('library.copies.destroy');
    Route::post('/library/copies/{bookCopy}/issue', [BookCopyController::class, 'issue'])->name('library.copies.issue');
    Route::get('/library/students', [LibraryLoanController::class, 'students'])->name('library.students.search');
    Route::get('/library/loans', [LibraryLoanController::class, 'index'])->name('library.loans.index');
    Route::post('/library/loans/{libraryLoan}/return', [LibraryLoanController::class, 'recordReturn'])->name('library.loans.return');
    Route::post('/library/loans/{libraryLoan}/renew', [LibraryLoanController::class, 'renew'])->name('library.loans.renew');

    Route::resource('notices', NoticeController::class);
    Route::post('/notices/{notice}/publish', [NoticeController::class, 'publish'])->name('notices.publish');
    Route::post('/notices/{notice}/withdraw', [NoticeController::class, 'withdraw'])->name('notices.withdraw');
    Route::post('/notifications/read-all', [NoticeController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NoticeController::class, 'markRead'])->name('notifications.read');

    Route::resource('subjects', SubjectController::class);

    Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::resource('staff', StaffController::class)->except(['create', 'edit']);
    Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::post('/departments', [OrganizationLookupController::class, 'storeDepartment'])->name('departments.store');
    Route::post('/designations', [OrganizationLookupController::class, 'storeDesignation'])->name('designations.store');
    Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');

    Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::resource('students', StudentController::class);
    Route::post('/students/{student}/enrolments', [StudentController::class, 'storeEnrolment'])->name('students.enrolments.store');
    Route::get('/students/{student}/id-card', StudentIdCardController::class)->name('students.id-card');
    Route::post('/students/{student}/transfers', [StudentTransferController::class, 'store'])->name('students.transfers.store');
    Route::get('/students/{student}/photo', [StudentDocumentController::class, 'photo'])->name('students.photo');
    Route::post('/students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');
    Route::get('/students/{student}/documents/{document}', [StudentDocumentController::class, 'download'])->name('students.documents.download');
    Route::post('/students/{student}/guardians/{guardian}/account', [GuardianAccountController::class, 'link'])->scopeBindings()->name('students.guardians.account.link');
    Route::delete('/students/{student}/guardians/{guardian}/account', [GuardianAccountController::class, 'unlink'])->scopeBindings()->name('students.guardians.account.unlink');
    Route::post('/students/{student}/guardians/{guardian}/account/invite', [GuardianAccountController::class, 'invite'])->scopeBindings()->name('students.guardians.account.invite');
    Route::get('/students/{student}/notes/{note}/attachment', [StudentNoteController::class, 'download'])->name('students.notes.attachment');
    Route::resource('students.notes', StudentNoteController::class)->scoped();

    Route::resource('admissions', AdmissionController::class);
    Route::patch('/admissions/{admission}/status', [AdmissionController::class, 'transition'])->name('admissions.transition');
    Route::post('/admissions/{admission}/documents', [AdmissionDocumentController::class, 'store'])->name('admissions.documents.store');
    Route::get('/admissions/{admission}/documents/{document}', [AdmissionDocumentController::class, 'download'])->name('admissions.documents.download');
    Route::post('/admissions/{admission}/follow-ups', [AdmissionFollowUpController::class, 'store'])->name('admissions.follow-ups.store');
    Route::patch('/admission-follow-ups/{followUp}/complete', [AdmissionFollowUpController::class, 'complete'])->name('admissions.follow-ups.complete');

    Route::resource('users', UserController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);
    
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'update'])->name('roles.permissions.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('roles.destroy');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:permissions.view')->name('permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('can:permissions.delete')->name('permissions.destroy');
});

Route::prefix('supplies')->name('supplies.')->group(function (): void {
    Route::get('/', [SuppliesDashboardController::class, 'index'])->name('dashboard');
 
    // Inventory items and their stock
    Route::resource('items', InventoryItemController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->parameters(['items' => 'item']);
    Route::get('items/{item}/adjust', [InventoryItemController::class, 'adjust'])->name('items.adjust');
    Route::post('items/{item}/adjust', [InventoryItemController::class, 'storeAdjustment'])->name('items.adjust.store');
    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('movements.index');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['categories' => 'category']);
 
    // Suppliers and stock purchases
    Route::resource('suppliers', SupplierController::class)->except('destroy')->parameters(['suppliers' => 'supplier']);
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show'])->parameters(['purchases' => 'purchase']);
    Route::post('purchases/{purchase}/reverse', [PurchaseController::class, 'reverse'])->name('purchases.reverse');
 
    // Student sales
    Route::get('students/search', [StudentSupplyController::class, 'search'])->name('students.search');
    Route::get('students/{student}', [StudentSupplyController::class, 'show'])->name('students.show');
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show'])->parameters(['sales' => 'sale']);
    Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::post('sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store');
    Route::post('sales/{sale}/return', [SaleController::class, 'returnItems'])->name('sales.return');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('sales/{sale}/items/{saleItem}/deliver', [SaleController::class, 'deliver'])->scopeBindings()->name('sales.items.deliver');
 
    // Class requirements
    Route::resource('requirements', RequirementController::class)->except('show')->parameters(['requirements' => 'requirement']);
    Route::post('requirements/{requirement}/external', [RequirementController::class, 'markExternal'])->name('requirements.external');
    Route::delete('requirements/{requirement}/external', [RequirementController::class, 'unmarkExternal'])->name('requirements.external.remove');
 
    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
});
