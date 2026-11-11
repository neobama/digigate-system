<?php

namespace App\Http\Controllers;

use App\Services\AgentApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentApiController extends Controller
{
    public function __construct(private AgentApiService $agent) {}

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'digigate-agent-api',
            'version' => 'v1',
            'read_only' => true,
        ]);
    }

    public function tools(): JsonResponse
    {
        return response()->json([
            'description' => 'DigiGate ERP read-only Agent API for Claude Code / MCP Virtual Employee',
            'auth' => 'Authorization: Bearer <AGENT_API_TOKEN> or X-Digigate-Agent-Token',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/agent/v1/health', 'tool' => 'health'],
                ['method' => 'GET', 'path' => '/api/agent/v1/overview', 'tool' => 'get_overview'],
                ['method' => 'GET', 'path' => '/api/agent/v1/search?q=', 'tool' => 'search_erp'],
                ['method' => 'GET', 'path' => '/api/agent/v1/employees', 'tool' => 'list_employees'],
                ['method' => 'GET', 'path' => '/api/agent/v1/employees/{id}', 'tool' => 'get_employee'],
                ['method' => 'GET', 'path' => '/api/agent/v1/tasks', 'tool' => 'list_tasks'],
                ['method' => 'GET', 'path' => '/api/agent/v1/attendance/summary?date=YYYY-MM-DD', 'tool' => 'attendance_summary'],
                ['method' => 'GET', 'path' => '/api/agent/v1/attendance', 'tool' => 'list_attendance'],
                ['method' => 'GET', 'path' => '/api/agent/v1/components', 'tool' => 'list_components'],
                ['method' => 'GET', 'path' => '/api/agent/v1/components/stock', 'tool' => 'component_stock_summary'],
                ['method' => 'GET', 'path' => '/api/agent/v1/assemblies', 'tool' => 'list_assemblies'],
                ['method' => 'GET', 'path' => '/api/agent/v1/invoices', 'tool' => 'list_invoices'],
                ['method' => 'GET', 'path' => '/api/agent/v1/leave-requests', 'tool' => 'list_leave_requests'],
                ['method' => 'GET', 'path' => '/api/agent/v1/device-types', 'tool' => 'list_device_types'],
                ['method' => 'GET', 'path' => '/api/agent/v1/logbooks', 'tool' => 'list_logbooks'],
                ['method' => 'GET', 'path' => '/api/agent/v1/expenses', 'tool' => 'list_expenses'],
                ['method' => 'GET', 'path' => '/api/agent/v1/incomes', 'tool' => 'list_incomes'],
                ['method' => 'GET', 'path' => '/api/agent/v1/reimbursements', 'tool' => 'list_reimbursements'],
                ['method' => 'GET', 'path' => '/api/agent/v1/cashbons', 'tool' => 'list_cashbons'],
                ['method' => 'GET', 'path' => '/api/agent/v1/budget-requests', 'tool' => 'list_budget_requests'],
                ['method' => 'GET', 'path' => '/api/agent/v1/salary-payments', 'tool' => 'list_salary_payments'],
                ['method' => 'GET', 'path' => '/api/agent/v1/financial-summary', 'tool' => 'financial_summary'],
                ['method' => 'GET', 'path' => '/api/agent/v1/warranty-claims', 'tool' => 'list_warranty_claims'],
                ['method' => 'GET', 'path' => '/api/agent/v1/device-returns', 'tool' => 'list_device_returns'],
                ['method' => 'GET', 'path' => '/api/agent/v1/documents', 'tool' => 'list_documents'],
                ['method' => 'GET', 'path' => '/api/agent/v1/activity-logs', 'tool' => 'list_activity_logs'],
            ],
        ]);
    }

    public function overview(): JsonResponse
    {
        return response()->json($this->agent->overview());
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->agent->search($data['q'], (int) ($data['limit'] ?? 10)));
    }

    public function employees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'active_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->employees(
                $data['q'] ?? null,
                array_key_exists('active_only', $data) ? (bool) $data['active_only'] : true,
                (int) ($data['limit'] ?? 50),
            ),
        ]);
    }

    public function employee(string $id): JsonResponse
    {
        $employee = $this->agent->employeeById($id);
        if (! $employee) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $employee]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'uuid'],
            'category' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->tasks(
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['status'] ?? null,
                $data['employee_id'] ?? null,
                $data['category'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function attendanceSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $data['date'] ?? now(config('app.timezone', 'Asia/Jakarta'))->toDateString();

        return response()->json($this->agent->attendanceSummaryForDate($date));
    }

    public function attendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->attendances(
                $data['date'] ?? null,
                $data['employee_id'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function components(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->components(
                $data['status'] ?? 'available',
                $data['name'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function componentStock(): JsonResponse
    {
        return response()->json(['data' => $this->agent->componentStockSummary()]);
    }

    public function assemblies(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_type' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->assemblies(
                $data['product_type'] ?? null,
                (int) ($data['limit'] ?? 50),
            ),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->invoices(
                $data['status'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 50),
            ),
        ]);
    }

    public function leaveRequests(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->leaveRequests(
                $data['status'] ?? null,
                $data['employee_id'] ?? null,
                (int) ($data['limit'] ?? 50),
            ),
        ]);
    }

    public function deviceTypes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active_only' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->agent->deviceTypes(
                array_key_exists('active_only', $data) ? (bool) $data['active_only'] : true,
            ),
        ]);
    }

    public function logbooks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->logbooks(
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['employee_id'] ?? null,
                (int) ($data['limit'] ?? 50),
            ),
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'fund_source' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->expenses(
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['fund_source'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function incomes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->incomes(
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function reimbursements(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->reimbursements(
                $data['status'] ?? null,
                $data['employee_id'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function cashbons(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->cashbons(
                $data['status'] ?? null,
                $data['employee_id'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function budgetRequests(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->budgetRequests(
                $data['status'] ?? null,
                $data['employee_id'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function salaryPayments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'employee_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->salaryPayments(
                isset($data['month']) ? (int) $data['month'] : null,
                isset($data['year']) ? (int) $data['year'] : null,
                $data['employee_id'] ?? null,
                $data['status'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function financialSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json(
            $this->agent->financialSummary(
                isset($data['month']) ? (int) $data['month'] : null,
                isset($data['year']) ? (int) $data['year'] : null,
            )
        );
    }

    public function warrantyClaims(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->warrantyClaims(
                $data['status'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function deviceReturns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->deviceReturns(
                $data['status'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->documents(
                $data['category'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'model_type' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->agent->activityLogs(
                $data['action'] ?? null,
                $data['model_type'] ?? null,
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
        ]);
    }
}
