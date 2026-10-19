<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DonoAssistantController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\RepairPortalController;
use App\Http\Controllers\SalarySlipController;

// Root route now handled by Filament admin panel
// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/invoices/{invoice}/proforma-pdf', [InvoicePdfController::class, 'proforma'])
    ->name('invoices.proforma.pdf');

Route::get('/invoices/{invoice}/paid-pdf', [InvoicePdfController::class, 'paid'])
    ->name('invoices.paid.pdf');

Route::get('/invoices/{invoice}/surat-jalan-pdf', [InvoicePdfController::class, 'suratJalan'])
    ->name('invoices.surat-jalan.pdf');

Route::get('/employees/{employee}/salary-slip', [SalarySlipController::class, 'show'])
    ->name('employee.salary-slip');

Route::get('/salary-slips/{salaryPayment}', [SalarySlipController::class, 'showBySalaryPayment'])
    ->name('salary-payments.slip');

Route::post('/dono/chat', [DonoAssistantController::class, 'chat'])
    ->middleware('auth')
    ->name('dono.chat');

Route::get('/dono/chat/{jobId}', [DonoAssistantController::class, 'status'])
    ->middleware('auth')
    ->whereUuid('jobId')
    ->name('dono.chat.status');

// Device Return portal dinonaktifkan permanen — diganti portal garansi/service.
$repairPortalRoutes = function () {
    Route::get('/', [RepairPortalController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('create');
    Route::get('/captcha', [RepairPortalController::class, 'captcha'])
        ->middleware('throttle:20,1')
        ->name('captcha');
    Route::post('/', [RepairPortalController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('store');
    Route::get('/success', [RepairPortalController::class, 'success'])
        ->middleware('throttle:30,1')
        ->name('success');
};

if (app()->environment('production')) {
    Route::domain('repair.digigate.id')
        ->name('repair.portal.')
        ->group($repairPortalRoutes);
} else {
    // Dev fallback: /repair-portal
    Route::prefix('repair-portal')
        ->name('repair.portal.')
        ->group($repairPortalRoutes);
}
