<?php

use App\Http\Controllers\AgentApiController;
use App\Http\Middleware\AuthenticateAgentApi;
use Illuminate\Support\Facades\Route;

Route::prefix('agent/v1')
    ->middleware([
        AuthenticateAgentApi::class,
        'throttle:'.max(1, (int) config('agent_api.rate_limit_per_minute', 120)).',1',
    ])
    ->group(function () {
        Route::get('/health', [AgentApiController::class, 'health']);
        Route::get('/tools', [AgentApiController::class, 'tools']);
        Route::get('/overview', [AgentApiController::class, 'overview']);
        Route::get('/search', [AgentApiController::class, 'search']);
        Route::get('/employees', [AgentApiController::class, 'employees']);
        Route::get('/employees/{id}', [AgentApiController::class, 'employee']);
        Route::get('/tasks', [AgentApiController::class, 'tasks']);
        Route::get('/attendance/summary', [AgentApiController::class, 'attendanceSummary']);
        Route::get('/attendance', [AgentApiController::class, 'attendance']);
        Route::get('/components/stock', [AgentApiController::class, 'componentStock']);
        Route::get('/components', [AgentApiController::class, 'components']);
        Route::get('/assemblies', [AgentApiController::class, 'assemblies']);
        Route::get('/invoices', [AgentApiController::class, 'invoices']);
        Route::get('/leave-requests', [AgentApiController::class, 'leaveRequests']);
        Route::get('/device-types', [AgentApiController::class, 'deviceTypes']);
        Route::get('/logbooks', [AgentApiController::class, 'logbooks']);
        Route::get('/expenses', [AgentApiController::class, 'expenses']);
        Route::get('/incomes', [AgentApiController::class, 'incomes']);
        Route::get('/reimbursements', [AgentApiController::class, 'reimbursements']);
        Route::get('/cashbons', [AgentApiController::class, 'cashbons']);
        Route::get('/budget-requests', [AgentApiController::class, 'budgetRequests']);
        Route::get('/salary-payments', [AgentApiController::class, 'salaryPayments']);
        Route::get('/financial-summary', [AgentApiController::class, 'financialSummary']);
        Route::get('/warranty-claims', [AgentApiController::class, 'warrantyClaims']);
        Route::get('/device-returns', [AgentApiController::class, 'deviceReturns']);
        Route::get('/documents', [AgentApiController::class, 'documents']);
        Route::get('/activity-logs', [AgentApiController::class, 'activityLogs']);
    });
