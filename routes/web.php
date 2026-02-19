<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoicePdfController;
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

// (Device Return portal sementara dinonaktifkan)
