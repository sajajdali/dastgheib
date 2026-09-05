<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Controllers
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\ContactController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientMediaController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\HumanResourceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ServiceFollowupController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CentralServiceTicketController;
use App\Http\Controllers\StoreCheckoutController;
use App\Http\Controllers\Api\CompletionSmsController;
use App\Http\Controllers\Api\PhotoComparisonController;
use App\Http\Controllers\Api\BeautyAnnotationController;
use App\Http\Controllers\Api\AppointmentNoteController;
use App\Http\Controllers\Api\AttendanceMonthController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\PersonalReportController;
use App\Http\Controllers\Api\PayrollReportController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AutomaticSmsScenarioController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ClinicReportController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\CampaignController;




/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
//تنظیمات
Route::middleware('auth')->group(function () {
Route::get('/auth/user', [AuthController::class, 'user']);
Route::put('/auth/user', [AuthController::class, 'updateUser']);
Route::post('/auth/user/photo', [AuthController::class, 'uploadUserPhoto']);
Route::delete('/auth/user/photo', [AuthController::class, 'deleteUserPhoto']);
Route::get('/store/terms', [StoreCheckoutController::class, 'terms']);
Route::post('/store/checkout', [StoreCheckoutController::class, 'checkout']);
Route::get('/service-tickets', [CentralServiceTicketController::class, 'tenantIndex']);
Route::post('/service-tickets', [CentralServiceTicketController::class, 'tenantStore'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/calendar/events', [CalendarController::class, 'index']);
Route::put('/calendar/overrides/{date}', [CalendarController::class, 'saveOverride'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/settings/internal', [SettingController::class, 'saveInternalSettings'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/settings/attendance-status', [SettingController::class, 'saveAttendanceStatus'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/settings/users/{user}/photo', [SettingController::class, 'uploadUserPhoto'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::delete('/settings/users/{user}', [SettingController::class, 'destroyUser'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/settings/sms', [SettingController::class, 'saveSmsSettings'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::get('/automatic-sms-scenarios', [AutomaticSmsScenarioController::class, 'index'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/automatic-sms-projects', [AutomaticSmsScenarioController::class, 'storeProject'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::put('/automatic-sms-projects/{automaticSmsProject}', [AutomaticSmsScenarioController::class, 'updateProject'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::delete('/automatic-sms-projects/{automaticSmsProject}', [AutomaticSmsScenarioController::class, 'destroyProject'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/automatic-sms-scenarios', [AutomaticSmsScenarioController::class, 'store'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::put('/automatic-sms-scenarios/{automaticSmsScenario}', [AutomaticSmsScenarioController::class, 'update'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::delete('/automatic-sms-scenarios/{automaticSmsScenario}', [AutomaticSmsScenarioController::class, 'destroy'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/sms/completion', [CompletionSmsController::class, 'send']);
Route::post('/sms/payment-link', [CompletionSmsController::class, 'sendPaymentLink']);
Route::post('/sms/appointment', [CompletionSmsController::class, 'sendAppointment']);
Route::post('/sms/landing', [CompletionSmsController::class, 'sendLanding'])->middleware('permission:followups.view');
Route::post('/settings/access', [SettingController::class, 'saveAccessSettings'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:activity_logs.view');

// نقش‌ها و دسترسی‌ها
Route::get('/roles', [RoleController::class, 'index'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::post('/roles', [RoleController::class, 'store'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');
Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('role:مدیر سیستم|مدیر کل|super admin|super-admin');


//کیف پول 
Route::post('/patients/{id}/wallet/deposit', [PatientController::class, 'depositWallet']);
Route::post('/patients/{id}/wallet/withdraw', [PatientController::class, 'withdrawWallet']);
Route::get('/patients/{patient}/wallet/transactions', [PatientController::class, 'walletTransactions']);
Route::delete('/patients/{patient}/wallet/deposits/{transaction}', [PatientController::class, 'deleteBookingDeposit']);

// بیماران
Route::get('/patients/next-file-number', [PatientController::class, 'nextFileNumber'])->middleware('permission:patients.create');
Route::get('/patients/check-duplicate', [PatientController::class, 'checkDuplicate'])->middleware('permission:patients.create');
Route::post('/patients', [PatientController::class, 'store'])->middleware('permission:patients.create');
Route::get('/patients/find-by-phone/{phone}', [PatientController::class, 'findByPhone']);
Route::get('/patients/upcoming-birthdays', [PatientController::class, 'upcomingBirthdays'])->middleware('permission:patients.view');
Route::get('/patients/search', [PatientController::class, 'search'])->middleware('permission:patients.view');
Route::put('/patients/{id}', [PatientController::class, 'update'])->middleware('permission:patients.update');
Route::patch('/patients/{patient}/customer-level', [PatientController::class, 'updateCustomerLevel']);
Route::post('/patients/{patient}/profile-photo', [PatientController::class, 'uploadProfilePhoto']);
Route::get('/patients/{patient}/media', [PatientMediaController::class, 'index']);
Route::post('/patients/{patient}/media/folders', [PatientMediaController::class, 'storeFolder']);
Route::delete('/patients/{patient}/media/folders/{folder}', [PatientMediaController::class, 'destroyFolder']);
Route::post('/patients/{patient}/media/files', [PatientMediaController::class, 'storeFiles']);
Route::patch('/patients/{patient}/media/files/{media}', [PatientMediaController::class, 'update']);
Route::post('/patients/{patient}/media/files/{media}', [PatientMediaController::class, 'update']);
Route::delete('/patients/{patient}/media/files/{media}', [PatientMediaController::class, 'destroy']);
Route::get('/photo-comparisons', [PhotoComparisonController::class, 'index'])->middleware('permission:photos.view');
Route::get('/beauty/context', [BeautyAnnotationController::class, 'context']);
Route::get('/beauty/annotations', [BeautyAnnotationController::class, 'index']);
Route::get('/patients/{patient}/beauty', [BeautyAnnotationController::class, 'show']);
Route::post('/patients/{patient}/beauty/annotations', [BeautyAnnotationController::class, 'store']);
Route::patch('/patients/{patient}/beauty/annotations/{annotation}', [BeautyAnnotationController::class, 'update']);
Route::delete('/patients/{patient}/beauty/annotations/{annotation}', [BeautyAnnotationController::class, 'destroy']);

// خدمات
Route::post('/services', [ServiceController::class, 'store']);
Route::get('/appointments/patient-history', [AppointmentController::class, 'patientHistory']);

// منابع انسانی
Route::get('/doctors', [HumanResourceController::class, 'getDoctors'])->middleware('permission:resources.view|appointments.view|attendance.view');
Route::post('/doctors', [HumanResourceController::class, 'saveDoctors'])->middleware('permission:resources.doctors');
Route::post('/doctors/{doctor}/photo', [HumanResourceController::class, 'uploadDoctorPhoto'])->middleware('permission:resources.doctors');

Route::get('/staff', [HumanResourceController::class, 'getStaff']);
Route::post('/staff', [HumanResourceController::class, 'saveStaff']);
Route::post('/staff/{staff}/photo', [HumanResourceController::class, 'uploadStaffPhoto']);
Route::get('/attendance/months', [AttendanceMonthController::class, 'index'])->middleware('permission:attendance.view|attendance.manage');
Route::post('/attendance/months', [AttendanceMonthController::class, 'store'])->middleware('permission:attendance.view|attendance.manage');
Route::patch('/attendance/months/{attendanceMonth}', [AttendanceMonthController::class, 'update'])->middleware('permission:attendance.view|attendance.manage');
Route::delete('/attendance/months/{attendanceMonth}', [AttendanceMonthController::class, 'destroy'])->middleware('permission:attendance.manage');
Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'update', 'destroy']);
Route::get('/personal-report', [PersonalReportController::class, 'show']);
Route::get('/clinic-report/cancellation-rate', [ClinicReportController::class, 'cancellationRate'])->middleware('permission:reports.view');
Route::get('/reports/dashboard', [ClinicReportController::class, 'dashboard'])->middleware('permission:reports.view');
Route::get('/reports/drilldown/{metric}', [ClinicReportController::class, 'drilldown'])->middleware('permission:reports.view');
Route::get('/reports/export', [ClinicReportController::class, 'export'])->middleware('permission:reports.view');
Route::apiResource('expenses', ExpenseController::class)->middleware('permission:bills.view');
Route::apiResource('campaigns', CampaignController::class)->middleware('permission:followups.view');
Route::get('/payroll/resources', [PayrollReportController::class, 'resources'])->middleware('permission:payroll.view|reports.staff|reports.doctors|reports.financial');
Route::get('/payroll/report', [PayrollReportController::class, 'show'])->middleware('permission:payroll.view|reports.staff|reports.doctors|reports.financial');
Route::patch('/payroll/lines/{line}', [PayrollReportController::class, 'updateLine'])->middleware('permission:payroll.view|reports.financial');
Route::post('/payroll/lines/{line}/restore', [PayrollReportController::class, 'restoreLine'])->middleware('permission:payroll.view|reports.financial');
Route::delete('/payroll/lines/{line}', [PayrollReportController::class, 'destroyLine'])->middleware('permission:payroll.view|reports.financial');
Route::get('/payment-options', [HumanResourceController::class, 'getPaymentOptions']);
Route::post('/payment-options', [HumanResourceController::class, 'savePaymentOptions']);
Route::get('/service-tags', [HumanResourceController::class, 'getServiceTags'])->middleware('permission:resources.view|inventory.view|appointments.view|followups.view');
Route::post('/service-tags', [HumanResourceController::class, 'saveServiceTags'])->middleware('permission:resources.view|inventory.update');

// نوبت‌دهی
Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->middleware('permission:appointments.view');
Route::get('/appointments/hidden-days', [AppointmentController::class, 'hiddenDays'])->middleware('permission:appointments.view');
Route::get('/appointments/balance-audits', [AppointmentController::class, 'balanceAudits'])->middleware('permission:reports.financial');
Route::post('/patients/{patient}/debt-payment', [AppointmentController::class, 'payPatientDebt'])->middleware('permission:appointments.create|appointments.update');
Route::post('/appointments/row', [AppointmentController::class, 'saveRow'])->middleware('permission:appointments.create|appointments.update');
Route::post('/appointments', [AppointmentController::class, 'saveAppointments'])->middleware('permission:appointments.create|appointments.update');
Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->middleware('permission:appointments.update');
Route::post('/appointments/single', [AppointmentController::class, 'storeSingle'])->middleware('permission:appointments.create|appointments.update');
Route::post('/appointments/hide-day', [AppointmentController::class, 'hideDay'])->middleware('permission:appointments.create|appointments.update');
Route::post('/appointments/restore-day', [AppointmentController::class, 'restoreDay'])->middleware('permission:appointments.create|appointments.update');
Route::get('/appointment-notes', [AppointmentNoteController::class, 'index']);
Route::post('/appointment-notes', [AppointmentNoteController::class, 'store']);
Route::delete('/appointment-notes/{message}', [AppointmentNoteController::class, 'destroy']);

// انبار
Route::get('/inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view|appointments.view');
Route::get('/inventory/context', [InventoryController::class, 'context'])->middleware('permission:inventory.view|appointments.view|resources.view');
Route::post('/inventory/addons', [InventoryController::class, 'storeAddonDefinitions'])->middleware('permission:inventory.create|inventory.update');
Route::post('/inventory', [InventoryController::class, 'store'])->middleware('permission:inventory.create|inventory.update');
Route::post('/inventory/adjust-stock', [InventoryController::class, 'adjustStock'])->middleware('permission:inventory.update');
Route::get('/inventory/{inventory}/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
Route::get('/service-followups', [ServiceFollowupController::class, 'index'])->middleware('permission:followups.view');
Route::patch('/service-followups/{serviceFollowup}', [ServiceFollowupController::class, 'update'])->middleware('permission:followups.view');

// تماس‌ها (FlwUp)
Route::get('/contacts', [ContactController::class, 'index']);
Route::post('/contacts', [ContactController::class, 'store']);

// کانال‌ها
Route::get('/channels', [ChannelController::class, 'index']);
Route::post('/channels', [ChannelController::class, 'store']);
Route::post('/channels/{channel}/icon', [ChannelController::class, 'uploadIcon']);
Route::delete('/channels/{id}', [ChannelController::class, 'destroy']);
});
