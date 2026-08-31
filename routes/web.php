<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WorkQueueController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\PipelineStageController;
use App\Http\Controllers\DashboardLayoutController;
use App\Http\Controllers\PipelineTransitionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CustomReportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentGenerateController;
use App\Http\Controllers\DocumentUploadController;
use App\Http\Controllers\DocumentDriveController;
use App\Http\Controllers\DocumentBlankController;
use App\Http\Controllers\OnlyOfficeController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\GoogleDriveOAuthController;
use App\Http\Controllers\CaseChatController;
use App\Http\Controllers\CasePdfController;
use App\Http\Controllers\AccountingExportController;
use App\Http\Controllers\SecuritySettingsController;
use App\Http\Controllers\UserMailboxController;
use App\Http\Controllers\IndustryController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\MentionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Api\CaseApiController;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('kanban.index') : redirect()->route('login');
});

Route::middleware(['auth', 'locale'])->group(function () {

    Route::get('/workqueue', [WorkQueueController::class, 'index'])->name('workqueue.index');
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::middleware('module:kanban')->group(function () {
        Route::get('/kanban', [KanbanController::class, 'index'])->name('kanban.index');
        Route::post('/kanban/{case}/move', [KanbanController::class, 'move'])->name('kanban.move');
    });

    Route::resource('cases', CaseController::class);
    Route::post('/cases-bulk-action', [CaseController::class, 'bulkAction'])->name('cases.bulk-action');
    Route::post('/cases-views', [CaseController::class, 'storeView'])->name('cases.views.store');
    Route::delete('/cases-views/{savedView}', [CaseController::class, 'destroyView'])->name('cases.views.destroy');
    Route::post('/cases/{case}/activities', [CaseController::class, 'storeActivity'])->name('cases.activities.store');
    Route::post('/cases/{case}/payments', [CaseController::class, 'storePayment'])->name('cases.payments.store');
    Route::post('/cases/{case}/change-status', [CaseController::class, 'changeStatus'])->name('cases.change-status');
    Route::post('/activities/{activity}/react', [CaseController::class, 'reactActivity'])->name('activities.react');
    Route::post('/cases/{case}/deliveries', [DeliveryController::class, 'store'])->name('cases.deliveries.store');
    Route::post('/cases/{case}/receivables', [ReceivableController::class, 'store'])->name('cases.receivables.store');
    Route::post('/receivables/{receivable}/payments', [ReceivableController::class, 'addPayment'])->name('receivables.payments.store');
    Route::post('/cases/{case}/attachments', [AttachmentController::class, 'store'])->name('cases.attachments.store');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::middleware('module:contacts')->group(function () {
        Route::resource('organizations', OrganizationController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
        Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::post('/contacts/destroy-all', [ContactController::class, 'destroyAll'])->name('contacts.destroyAll');
        Route::get('/contacts/import/template', [ContactController::class, 'importTemplate'])->name('contacts.import.template');
        Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
        Route::resource('contacts', ContactController::class);
        Route::post('/contacts-bulk-action', [ContactController::class, 'bulkAction'])->name('contacts.bulk-action');
        Route::post('/organizations-bulk-action', [OrganizationController::class, 'bulkAction'])->name('organizations.bulk-action');
        Route::get('/contacts/{contact}/card', [ContactController::class, 'card'])->name('contacts.card');
        Route::post('/contacts/{contact}/confidential-notes', [ContactController::class, 'storeConfidentialNote'])
            ->name('contacts.confidential-notes.store');
    });

    Route::middleware('module:tasks')->group(function () {
        Route::resource('tasks', TaskController::class);
        Route::post('/tasks-bulk-action', [TaskController::class, 'bulkAction'])->name('tasks.bulk-action');
        Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::post('/tasks/{task}/checklist', [TaskController::class, 'addChecklistItem'])->name('tasks.checklist.store');
        Route::post('/checklist-items/{item}/toggle', [TaskController::class, 'toggleChecklistItem'])->name('checklist.toggle');
        Route::delete('/checklist-items/{item}', [TaskController::class, 'destroyChecklistItem'])->name('checklist.destroy');
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    });

    Route::middleware('module:documents')->group(function () {
        Route::resource('documents', DocumentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        // M11+: حذف سند جدا شده تا فقط کاربران دارای permission مربوطه (نه هر
        // کاربری که به ماژول اسناد دسترسی دارد) دکمه/مسیر حذف را داشته باشند.
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
            ->middleware('can:document.delete')->name('documents.destroy');
        Route::get('/documents/{document}/print', [DocumentController::class, 'print'])->name('documents.print');
        Route::post('/documents/{document}/revisions', [DocumentController::class, 'addRevision'])->name('documents.revisions.store');
        Route::post('/document-revisions/{revision}/approve', [DocumentController::class, 'approve'])->name('documents.revisions.approve');
        Route::post('/document-revisions/{revision}/publish', [DocumentController::class, 'publish'])->name('documents.revisions.publish');
        Route::get('/document-revisions/{revision}/download', [DocumentController::class, 'downloadRevision'])->name('documents.revisions.download');
        Route::get('/document-revisions/{revision}/download-pdf', [DocumentController::class, 'downloadPdf'])->name('documents.revisions.download-pdf');
        // M8: ارسال ایمیل سند — فقط Revisionِ منتشرشده (Rule 1 سند معماری).
        Route::post('/document-revisions/{revision}/email', [DocumentController::class, 'sendEmail'])->name('documents.revisions.email');
        // M6 — Option C: دانلود → ویرایش با Word/Excel واقعی روی کامپیوتر کاربر → آپلود مجدد.
        Route::post('/document-revisions/{revision}/upload-edit', [DocumentController::class, 'uploadEdit'])->name('documents.revisions.upload-edit');
        // M21: فهرست اسناد → دکمه‌ی «ساخت کپی» روی هر ردیف (هر Revision).
        Route::post('/document-revisions/{revision}/copy', [DocumentController::class, 'copyRevision'])->name('documents.revisions.copy');
        // M24: حذف تکیِ یک Revision بدون حذف کل سند.
        Route::delete('/document-revisions/{revision}', [DocumentController::class, 'destroyRevision'])
            ->middleware('can:document.delete')->name('documents.revisions.destroy');
        // M35: انتخابِ قالبِ دیگر (غیر از قالبِ سندِ مادر) — هم روی یک Draftِ
        // موجودِ قابل‌ویرایش (mode=in_place)، هم موقعِ ساختِ یک Draftِ تازه از
        // رویِ نسخه‌ای دیگر (mode=new_draft).
        Route::get('/document-revisions/{revision}/template', [DocumentController::class, 'templateForm'])->name('documents.revisions.template-form');
        Route::post('/document-revisions/{revision}/template', [DocumentController::class, 'templateStore'])->name('documents.revisions.template-store');
        // M11: ویرایش آنلاین (ONLYOFFICE) — این فقط صفحه‌ی ویجت است (session-authenticated)؛
        // نقاط download/callback که خودِ Document Server صدا می‌زند در routes/api.php هستند.
        Route::get('/document-revisions/{revision}/edit-online', [OnlyOfficeController::class, 'editOnline'])->name('documents.revisions.edit-online');
        Route::post('/documents/{document}/new-draft', [DocumentController::class, 'newDraft'])->name('documents.new-draft');

        // M4: ساخت سند از قالب واقعی Word/Excel — عمداً از Route::resource('documents', ...)
        // بالا جدا است (نه create/store همان resource) چون یک مسیر کاملاً متفاوت است:
        // قالب واقعی + template_fields + شماره‌گذاری دیرهنگام، نه محتوای HTML قدیمی.
        // «/documents/generate/create» سه بخشی است، پس با «/documents/{document}» دو
        // بخشی (GET) هرگز تداخل نمی‌کند؛ «/documents/generate» POST هم چون resource
        // هیچ POST ای روی «/documents/{document}» ثبت نکرده (فقط GET/PUT/PATCH/DELETE)، ایمن است.
        Route::get('/documents/generate/create', [DocumentGenerateController::class, 'create'])->name('documents.generate.create');
        Route::post('/documents/generate', [DocumentGenerateController::class, 'store'])->name('documents.generate.store');

        // M9الف: آوردن فایل موجود (نه رندرشده از قالب) به‌عنوان سند — همان استدلال
        // ترتیب/تداخل روت‌های بالا (سه‌بخشی create، POST بدون تداخل با resource).
        Route::get('/documents/upload/create', [DocumentUploadController::class, 'create'])->name('documents.upload.create');
        Route::post('/documents/upload', [DocumentUploadController::class, 'store'])->name('documents.upload.store');

        // M9ب: انتخاب فایل از Google Drive متصل‌شده (همان اتصال بخش Backup) و
        // ثبت آن به‌عنوان سند — دانلود سمت سرور با توکن ذخیره‌شده، بدون SDK جدید.
        Route::get('/documents/drive/create', [DocumentDriveController::class, 'create'])->name('documents.drive.create');
        Route::post('/documents/drive/import', [DocumentDriveController::class, 'import'])->name('documents.drive.import');

        // M12: «ایجاد سند خالی» (منوی سند جدید) — همان استدلال ترتیب/تداخل روت‌های بالا.
        Route::get('/documents/blank/create', [DocumentBlankController::class, 'create'])->name('documents.blank.create');
        Route::post('/documents/blank', [DocumentBlankController::class, 'store'])->name('documents.blank.store');

        // مدیریت قالب Word/Excel واقعی (M2 — بخش ۹ سند معماری)
        // نکته‌ی ترتیب: /templates/create باید قبل از /templates/{template} ثبت شود
        // وگرنه Laravel «create» را به‌عنوان {template} تفسیر می‌کند (route-model binding).
        Route::get('/templates', [TemplateController::class, 'index'])
            ->middleware('can:template.view')->name('templates.index');
        Route::get('/templates/create', [TemplateController::class, 'create'])
            ->middleware('can:template.create')->name('templates.create');
        Route::post('/templates', [TemplateController::class, 'store'])
            ->middleware('can:template.create')->name('templates.store');
        Route::get('/templates/{template}', [TemplateController::class, 'show'])
            ->middleware('can:template.view')->name('templates.show');
        Route::post('/templates/{template}/versions', [TemplateController::class, 'storeVersion'])
            ->middleware('can:template.edit')->name('templates.versions.store');
        // بند ۶ سند معماری: وصل‌کردن جای‌نگه‌دارهای کشف‌شده به مسیر داده (binding).
        Route::post('/templates/{template}/fields', [TemplateController::class, 'updateFields'])
            ->middleware('can:template.edit')->name('templates.fields.update');
        Route::post('/templates/{template}/activate', [TemplateController::class, 'activate'])
            ->middleware('can:template.edit')->name('templates.activate');
        Route::post('/templates/{template}/set-default', [TemplateController::class, 'setDefault'])
            ->middleware('can:template.set_default')->name('templates.set-default');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])
            ->middleware('can:template.delete')->name('templates.destroy');
    });

    Route::middleware('module:email')->group(function () {
        Route::get('/emails', [EmailController::class, 'index'])->name('emails.index');
        Route::get('/emails/case-attachments/{case}', [\App\Http\Controllers\EmailController::class, 'caseAttachments'])->name('emails.case-attachments');
        Route::get('/emails/case-documents/{case}', [\App\Http\Controllers\EmailController::class, 'caseDocuments'])->name('emails.case-documents');
        // باگ قدیمی رفع شد (قبلاً در «گزارش‌نشده به کاربر» فهرست شده بود): این
        // روت به متد create() اشاره می‌کرد که اصلاً در EmailController وجود
        // نداشت — یعنی صفحه‌ی «نوشتن ایمیل» همیشه ۵۰۰ می‌داد. متد واقعی نامش
        // compose() است؛ چون همین چک‌این ویژگی جدیدی (پیوست‌کردن اسناد) را
        // دقیقاً به همین صفحه اضافه می‌کند، رفعش الان ضروری بود.
        Route::get('/emails/compose', [EmailController::class, 'compose'])->name('emails.create');
        Route::post('/emails', [EmailController::class, 'store'])->name('emails.store');
        Route::post('/emails/import', [EmailController::class, 'import'])->name('emails.import');

        // v19 features
        Route::post('/cases/{case}/chat', [CaseChatController::class, 'store'])->name('cases.chat.store');
        Route::get('/cases/{case}/pdf', CasePdfController::class)->name('cases.pdf');
        Route::get('/export/accounting/payments.csv', [AccountingExportController::class, 'payments'])->name('export.accounting.payments');

        Route::post('/emails/{email}/link', [EmailController::class, 'link'])->name('emails.link');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/export/cases.csv', [ExportController::class, 'casesCsv'])
        ->middleware('can:report.view')
        ->name('export.cases');

    Route::get('/backup/download', [BackupController::class, 'download'])
        ->middleware('can:settings.manage')
        ->name('backup.download');
    Route::get('/backup/import', [BackupController::class, 'importForm'])
        ->middleware('can:settings.manage')
        ->name('backup.import.form');
    Route::post('/backup/import', [BackupController::class, 'import'])
        ->middleware('can:settings.manage')
        ->name('backup.import');

    Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('twofactor.show');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('twofactor.enable');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('twofactor.disable');

    Route::prefix('reports')->middleware(['can:report.view', 'module:reports'])->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/pipeline', [ReportController::class, 'pipeline'])->name('reports.pipeline');
        Route::get('/performance', [ReportController::class, 'performance'])->name('reports.performance');
        Route::get('/tasks', [ReportController::class, 'tasks'])->name('reports.tasks');
        Route::get('/aging', [ReportController::class, 'aging'])->name('reports.aging');
        Route::get('/top-customers', [ReportController::class, 'topCustomers'])->name('reports.top-customers');
        Route::get('/top-contacts', [ReportController::class, 'topContacts'])->name('reports.top-contacts');
        Route::get('/top-followups', [ReportController::class, 'topFollowups'])->name('reports.top-followups');
        Route::get('/stuck-cases', [ReportController::class, 'stuckCases'])->name('reports.stuck-cases');
        Route::get('/received-count', [ReportController::class, 'receivedCount'])->name('reports.received-count');
        Route::get('/lost-count', [ReportController::class, 'lostCount'])->name('reports.lost-count');
        Route::get('/remaining-receivables', [ReportController::class, 'remainingReceivables'])->name('reports.remaining-receivables');
        Route::get('/conversion-funnel', [ReportController::class, 'conversionFunnel'])->name('reports.conversion-funnel');
        Route::get('/win-loss-monthly', [ReportController::class, 'winLossMonthly'])->name('reports.win-loss-monthly');
        Route::get('/pipeline-value', [ReportController::class, 'pipelineValue'])->name('reports.pipeline-value');
        Route::get('/expert-workload', [ReportController::class, 'expertWorkload'])->name('reports.expert-workload');
        Route::get('/cycle-time', [ReportController::class, 'cycleTime'])->name('reports.cycle-time');
        Route::get('/documents-by-type', [ReportController::class, 'documentsByType'])->name('reports.documents-by-type');
        Route::get('/overdue-tasks', [ReportController::class, 'overdueTasks'])->name('reports.overdue-tasks');
        Route::get('/vat-incoterm', [ReportController::class, 'vatIncoterm'])->name('reports.vat-incoterm');
        Route::get('/receivables-summary', [ReportController::class, 'receivablesSummary'])->name('reports.receivables-summary');
        Route::get('/invoice-gaps', [ReportController::class, 'invoiceGaps'])->name('reports.invoice-gaps');
        Route::get('/payments-period', [ReportController::class, 'paymentsPeriod'])->name('reports.payments-period');
        Route::get('/inactive-cases', [ReportController::class, 'inactiveCases'])->name('reports.inactive-cases');
        Route::get('/call-ratio', [ReportController::class, 'callRatio'])->name('reports.call-ratio');
        Route::get('/unmatched-emails', [ReportController::class, 'unmatchedEmails'])->name('reports.unmatched-emails');
        Route::get('/status-audit', [ReportController::class, 'statusAudit'])->name('reports.status-audit');
        Route::get('/one-time-orgs', [ReportController::class, 'oneTimeOrgs'])->name('reports.one-time-orgs');
        Route::get('/won-customers', [ReportController::class, 'wonCustomers'])->name('reports.won-customers');
        Route::get('/top-suppliers', [ReportController::class, 'topSuppliers'])->name('reports.top-suppliers');

        Route::get('/custom/create', [CustomReportController::class, 'create'])->name('reports.custom.create');
        Route::post('/custom', [CustomReportController::class, 'store'])->name('reports.custom.store');
        Route::get('/custom/{customReport}', [CustomReportController::class, 'show'])->name('reports.custom.show');
        Route::delete('/custom/{customReport}', [CustomReportController::class, 'destroy'])->name('reports.custom.destroy');
    });

    Route::prefix('settings')->middleware('can:settings.manage')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/modules', [SettingsController::class, 'modules'])->name('settings.modules');
        Route::post('/modules/{module}/toggle', [SettingsController::class, 'toggleModule'])->name('settings.modules.toggle');
        Route::get('/users', [SettingsController::class, 'users'])->name('settings.users');
        Route::post('/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
        Route::put('/users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
        Route::delete('/users/{user}', [SettingsController::class, 'destroyUser'])->name('settings.users.destroy');
        Route::get('/templates', [SettingsController::class, 'templates'])->name('settings.templates');
        Route::post('/templates', [SettingsController::class, 'storeTemplate'])->name('settings.templates.store');
        Route::post('/templates/preview', [SettingsController::class, 'previewTemplate'])->name('settings.templates.preview');
        Route::get('/appearance', [SettingsController::class, 'appearance'])->name('settings.appearance');
        Route::get('/numbering', [SettingsController::class, 'numbering'])->name('settings.numbering');
        Route::get('/tags', [TagController::class, 'index'])->name('settings.tags');
        Route::post('/tags', [TagController::class, 'store'])->name('settings.tags.store');
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('settings.tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('settings.tags.destroy');
        Route::get('/pipeline', [PipelineStageController::class, 'index'])->name('settings.pipeline');
        Route::get('/dashboard-layout', [DashboardLayoutController::class, 'index'])->name('settings.dashboard-layout');
        Route::post('/dashboard-layout', [DashboardLayoutController::class, 'save'])->name('settings.dashboard-layout.save');
        Route::get('/transitions', [PipelineTransitionController::class, 'index'])->name('settings.transitions');
        Route::post('/transitions', [PipelineTransitionController::class, 'save'])->name('settings.transitions.save');
        Route::post('/pipeline', [PipelineStageController::class, 'store'])->name('settings.pipeline.store');
        Route::put('/pipeline/{stage}', [PipelineStageController::class, 'update'])->name('settings.pipeline.update');
        Route::delete('/pipeline/{stage}', [PipelineStageController::class, 'destroy'])->name('settings.pipeline.destroy');
        Route::get('/automation', [AutomationController::class, 'index'])->name('settings.automation');
        Route::post('/automation', [AutomationController::class, 'store'])->name('settings.automation.store');
        Route::post('/automation/run-now', [AutomationController::class, 'runNow'])->name('settings.automation.run');
        Route::post('/automation/{rule}/toggle', [AutomationController::class, 'toggle'])->name('settings.automation.toggle');
        Route::delete('/automation/{rule}', [AutomationController::class, 'destroy'])->name('settings.automation.destroy');
        Route::post('/numbering', [SettingsController::class, 'saveNumbering'])->name('settings.numbering.save');
        // M39: پاک‌سازیِ number_base/document_number معلق‌مانده روی سندهای
        // soft-deleted قدیمی (رفعِ باگِ «UNIQUE constraint failed: documents.number_base»).
        Route::post('/numbering/cleanup-orphaned', [SettingsController::class, 'cleanupOrphanedNumbers'])->name('settings.numbering.cleanup-orphaned');
        Route::post('/appearance', [SettingsController::class, 'saveAppearance'])->name('settings.appearance.save');

        Route::get('/backup', [SettingsController::class, 'backupIndex'])->name('settings.backup');

        Route::get('/security', [SecuritySettingsController::class, 'index'])->name('settings.security');
        Route::get('/industries', [IndustryController::class, 'index'])->name('settings.industries');
        Route::post('/industries', [IndustryController::class, 'store'])->name('settings.industries.store');
        Route::put('/industries/{industry}', [IndustryController::class, 'update'])->name('settings.industries.update');
        Route::delete('/industries/{industry}', [IndustryController::class, 'destroy'])->name('settings.industries.destroy');
        Route::post('/security/mail', [SecuritySettingsController::class, 'saveMail'])->name('settings.security.mail');
        Route::post('/security/mail-test', [SecuritySettingsController::class, 'testMail'])->name('settings.security.mail.test');
        Route::post('/security/imap-test', [SecuritySettingsController::class, 'testImap'])->name('settings.security.imap.test');
        // M28: بررسیِ خودکارِ وضعیتِ تبدیل PDF (LibreOffice) از داخل برنامه —
        // بدون نیاز به لاگِ بیلدِ Railway.
        Route::post('/security/pdf-test', [SecuritySettingsController::class, 'testPdf'])->name('settings.security.pdf.test');
        Route::post('/security/field-acl', [SecuritySettingsController::class, 'saveFieldAcl'])->name('settings.security.field');
        Route::post('/security/reminders', [SecuritySettingsController::class, 'saveReminders'])->name('settings.security.reminders');
        Route::post('/security/retention', [SecuritySettingsController::class, 'saveRetention'])->name('settings.security.retention');

        Route::post('/backup/run', [SettingsController::class, 'backupRun'])->name('settings.backup.run');
        Route::post('/backup/schedule', [SettingsController::class, 'backupScheduleSave'])->name('settings.backup.schedule');
        Route::post('/backup/restore', [SettingsController::class, 'backupRestore'])->name('settings.backup.restore');
        Route::post('/backup/cloud', [SettingsController::class, 'backupCloudSave'])->name('settings.backup.cloud');
        Route::post('/backup/import-contacts', [SettingsController::class, 'dataHubImportContacts'])->name('settings.data.import.contacts');
        Route::post('/backup/factory-reset', [SettingsController::class, 'factoryReset'])->name('settings.factory.reset');
        Route::post('/backup/wipe-section', [SettingsController::class, 'wipeSection'])->name('settings.wipe.section');
        Route::post('/backup/import-trello', [SettingsController::class, 'dataHubImportTrello'])->name('settings.data.import.trello');
        // M9پ: Import گروهی اسناد قدیمی (Migration) — همان الگوی import-contacts/import-trello.
        Route::post('/backup/import-documents', [SettingsController::class, 'dataHubImportDocuments'])->name('settings.data.import.documents');
        Route::get('/backup/gdrive/connect', [GoogleDriveOAuthController::class, 'connect'])->name('settings.backup.gdrive.connect');
        Route::get('/backup/gdrive/callback', [GoogleDriveOAuthController::class, 'callback'])->name('settings.backup.gdrive.callback');
        Route::post('/backup/gdrive/disconnect', [GoogleDriveOAuthController::class, 'disconnect'])->name('settings.backup.gdrive.disconnect');
        Route::get('/backup/contacts-template', [\App\Http\Controllers\ContactController::class, 'importTemplate'])->name('settings.data.contacts.template');
        Route::get('/templates/{id}/edit', [SettingsController::class, 'templateEdit'])->name('settings.templates.edit');
        Route::put('/templates/{id}', [SettingsController::class, 'templateUpdate'])->name('settings.templates.update');
        Route::post('/templates/{id}/versions/{version}/restore', [SettingsController::class, 'templateRestoreVersion'])->name('settings.templates.version.restore');

    });

    Route::get('/mentions/users', [MentionController::class, 'users'])->name('mentions.users');
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    

    // Unified mail client — فاز B/C: Inbox + Compose
    Route::prefix('mail')->name('mail.')->group(function () {
        Route::get('/inbox', [\App\Http\Controllers\Mail\MailInboxController::class, 'index'])->name('inbox');
        Route::post('/inbox/sync', [\App\Http\Controllers\Mail\MailInboxController::class, 'sync'])->name('inbox.sync');
        Route::get('/message/{message}', [\App\Http\Controllers\Mail\MailInboxController::class, 'show'])->name('message.show');
        Route::post('/message/{message}/flag', [\App\Http\Controllers\Mail\MailInboxController::class, 'toggleFlag'])->name('message.flag');
        Route::post('/message/{message}/seen', [\App\Http\Controllers\Mail\MailInboxController::class, 'markSeen'])->name('message.seen');
        Route::post('/message/{message}/archive', [\App\Http\Controllers\Mail\MailInboxController::class, 'archive'])->name('message.archive');

        Route::post('/message/{message}/link-case', [\App\Http\Controllers\Mail\MailLinkController::class, 'linkCase'])->name('message.link-case');
        Route::post('/message/{message}/unlink-case', [\App\Http\Controllers\Mail\MailLinkController::class, 'unlinkCase'])->name('message.unlink-case');
        Route::post('/message/{message}/link-contact', [\App\Http\Controllers\Mail\MailLinkController::class, 'linkContact'])->name('message.link-contact');
        Route::get('/unmatched', [\App\Http\Controllers\Mail\MailLinkController::class, 'unmatched'])->name('unmatched');
        Route::get('/cases/search', [\App\Http\Controllers\Mail\MailLinkController::class, 'searchCases'])->name('cases.search');


        Route::get('/compose', [\App\Http\Controllers\Mail\MailComposeController::class, 'create'])->name('compose');
        Route::post('/compose', [\App\Http\Controllers\Mail\MailComposeController::class, 'send'])->name('compose.send');
        Route::post('/compose/draft', [\App\Http\Controllers\Mail\MailComposeController::class, 'saveDraft'])->name('compose.draft');
        Route::get('/signature', [\App\Http\Controllers\Mail\MailComposeController::class, 'signatureForm'])->name('signature');
        Route::post('/signature', [\App\Http\Controllers\Mail\MailComposeController::class, 'signatureSave'])->name('signature.save');
    });

    // Unified mail client — فاز A: مدیریت اکانت‌ها + sync (Admin)
    Route::prefix('mail/accounts')->name('mail.accounts.')->middleware('can:settings.manage')->group(function () {
        Route::get('/', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'store'])->name('store');
        Route::get('/{account}/edit', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'edit'])->name('edit');
        Route::put('/{account}', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'update'])->name('update');
        Route::delete('/{account}', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{account}/test', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'test'])->name('test');
        Route::post('/{account}/sync', [\App\Http\Controllers\Mail\MailAccountAdminController::class, 'syncNow'])->name('sync');
    });

    // Per-user corporate mailbox — کلاینت ایمیل شخصی (شبیه جیمیل: خواندن/پاسخ/فوروارد/پیوست/قالب)
    // نکته‌ی ترتیب روت‌ها: همه‌ی مسیرهای ثابت (settings/compose/sent/template/...) باید قبل از
    // روت‌های عمومی‌تر {uid} ثبت شوند وگرنه {uid} آن‌ها را قاپ می‌زند؛ برای اطمینان بیشتر هم
    // {uid} با where('[0-9]+') محدود به عدد شده تا هرگز با این مسیرهای متنی برخورد نکند.
    Route::get('/mailbox', [UserMailboxController::class, 'inbox'])->name('mailbox.inbox');
    Route::get('/mailbox/sent', [UserMailboxController::class, 'sent'])->name('mailbox.sent');
    Route::get('/mailbox/settings', [UserMailboxController::class, 'editOwn'])->name('mailbox.settings');
    Route::post('/mailbox/settings', [UserMailboxController::class, 'updateOwn'])->name('mailbox.settings.update');
    Route::post('/mailbox/test-smtp', [UserMailboxController::class, 'testSmtp'])->name('mailbox.test.smtp');
    Route::post('/mailbox/test-imap', [UserMailboxController::class, 'testImap'])->name('mailbox.test.imap');
    Route::get('/mailbox/compose', [UserMailboxController::class, 'composeForm'])->name('mailbox.compose');
    Route::post('/mailbox/compose', [UserMailboxController::class, 'composeSend'])->name('mailbox.compose.send');
    Route::get('/mailbox/template/{id}', [UserMailboxController::class, 'templatePreview'])->name('mailbox.template.preview');
    Route::post('/settings/users/{user}/mailbox', [UserMailboxController::class, 'updateForUser'])->name('settings.users.mailbox');
    Route::get('/mailbox/{uid}', [UserMailboxController::class, 'show'])->whereNumber('uid')->name('mailbox.show');
    Route::get('/mailbox/{uid}/attachment/{part}', [UserMailboxController::class, 'downloadAttachment'])->whereNumber('uid')->name('mailbox.attachment');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/theme', function (\Illuminate\Http\Request $request) {
        $theme = $request->input('theme', 'light');
        if (!in_array($theme, ['light', 'dark'], true)) {
            return response()->json(['ok' => false], 422);
        }
        session(['theme' => $theme]);
        try {
            \App\Models\AppSetting::set('theme', $theme);
        } catch (\Throwable $e) {
        }
        return response()->json(['ok' => true, 'theme' => $theme]);
    })->name('theme.save');

    Route::get('/locale/{locale}', function (string $locale) {
        if (!in_array($locale, ['fa', 'en'], true)) abort(404);
        session(['locale' => $locale]);
        if (auth()->check()) {
            try { auth()->user()->forceFill(['locale' => $locale])->save(); } catch (\Throwable $e) {}
        }
        return back();
    })->name('locale.switch.get');

    Route::post('/locale/{locale}', function (string $locale) {
        if (!in_array($locale, ['fa', 'en'], true)) abort(404);
        session(['locale' => $locale]);
        if (auth()->check()) {
            try { auth()->user()->forceFill(['locale' => $locale])->save(); } catch (\Throwable $e) {}
        }
        return back();
    })->name('locale.switch');

    Route::get('/custom-fields', [CustomFieldController::class, 'index'])
        ->middleware('can:settings.manage')->name('settings.custom-fields');
    Route::post('/custom-fields', [CustomFieldController::class, 'store'])
        ->middleware('can:settings.manage')->name('settings.custom-fields.store');
    Route::put('/custom-fields/{customField}', [CustomFieldController::class, 'update'])
        ->middleware('can:settings.manage')->name('settings.custom-fields.update');
    Route::delete('/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])
        ->middleware('can:settings.manage')->name('settings.custom-fields.destroy');

    Route::prefix('settings')->middleware('can:settings.manage')->group(function () {
        Route::get('/priorities', [PriorityController::class, 'index'])->name('settings.priorities');
        Route::post('/priorities', [PriorityController::class, 'store'])->name('settings.priorities.store');
        Route::put('/priorities', [PriorityController::class, 'update'])->name('settings.priorities.update');
        Route::delete('/priorities', [PriorityController::class, 'destroy'])->name('settings.priorities.destroy');

        Route::get('/holidays', [HolidayController::class, 'index'])->name('settings.holidays');
        Route::post('/holidays', [HolidayController::class, 'store'])->name('settings.holidays.store');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('settings.holidays.destroy');
        Route::post('/holidays/sync', [HolidayController::class, 'syncFromGithub'])->name('settings.holidays.sync');
    });

    Route::prefix('api')->group(function () {
        Route::get('/cases', [CaseApiController::class, 'index']);
        Route::get('/cases/{case}', [CaseApiController::class, 'show']);
    });
});

require __DIR__.'/auth.php';
