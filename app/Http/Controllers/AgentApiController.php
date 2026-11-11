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
}
