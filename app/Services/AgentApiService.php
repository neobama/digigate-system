<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\Attendance;
use App\Models\Component;
use App\Models\DeviceType;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Logbook;
use App\Models\Task;
use Carbon\Carbon;

class AgentApiService
{
    public function overview(): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::now($tz)->toDateString();

        return [
            'generated_at' => now($tz)->toIso8601String(),
            'timezone' => $tz,
            'counts' => [
                'employees_active' => Employee::query()->where('is_active', true)->count(),
                'tasks_open' => Task::query()->whereIn('status', ['pending', 'in_progress', 'late'])->count(),
                'components_available' => Component::query()->where('status', 'available')->count(),
                'invoices_proforma' => Invoice::query()->where('status', 'proforma')->count(),
                'invoices_paid' => Invoice::query()->where('status', 'paid')->count(),
                'leave_pending' => LeaveRequest::query()->where('status', 'pending')->count(),
                'assemblies_total' => Assembly::query()->count(),
            ],
            'attendance_today' => $this->attendanceSummaryForDate($today),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function employees(?string $q = null, bool $activeOnly = true, int $limit = 50): array
    {
        $query = Employee::query()->orderBy('name');
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if (filled($q)) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('position', 'like', "%{$q}%")
                    ->orWhere('nik', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (Employee $e) => $this->serializeEmployee($e))->all();
    }

    public function employeeById(string $id): ?array
    {
        $employee = Employee::query()->find($id);

        return $employee ? $this->serializeEmployee($employee, true) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasks(?string $from = null, ?string $to = null, ?string $status = null, ?string $employeeId = null, ?string $category = null, int $limit = 100): array
    {
        $query = Task::query()->with('employees:id,name')->orderByDesc('start_date');

        if ($from) {
            $query->whereDate('end_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('start_date', '<=', $to);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($employeeId) {
            $query->whereHas('employees', fn ($q) => $q->where('employees.id', $employeeId));
        }

        return $query->limit($limit)->get()->map(fn (Task $task) => [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'category' => $task->category?->value,
            'category_label' => $task->category?->label(),
            'start_date' => $task->start_date?->toDateString(),
            'end_date' => $task->end_date?->toDateString(),
            'start_time' => $task->start_time,
            'end_time' => $task->end_time,
            'status' => $task->status,
            'is_self_assigned' => (bool) $task->is_self_assigned,
            'assignees' => $task->employees->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ])->values()->all(),
        ])->all();
    }

    public function attendanceSummaryForDate(string $date): array
    {
        $service = app(AttendanceWorkingHoursService::class);
        $day = Carbon::parse($date, $service->timezone());
        $rows = $service->summariesForDate($day);

        $complete = [];
        $incomplete = [];
        $absent = [];

        foreach ($rows as $row) {
            /** @var Employee $employee */
            $employee = $row['employee'];
            $entry = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'tap_in' => $row['tap_in']?->recorded_at?->timezone($service->timezone())->format('H:i'),
                'tap_out' => $row['tap_out']?->recorded_at?->timezone($service->timezone())->format('H:i'),
                'working_hours' => $row['working_hours_label'],
                'is_complete' => (bool) $row['is_complete'],
            ];

            if (! $row['tap_in'] && ! $row['tap_out']) {
                $absent[] = $entry;
            } elseif ($row['is_complete']) {
                $complete[] = $entry;
            } else {
                $incomplete[] = $entry;
            }
        }

        return [
            'date' => $day->toDateString(),
            'complete_count' => count($complete),
            'incomplete_count' => count($incomplete),
            'absent_count' => count($absent),
            'complete' => $complete,
            'incomplete' => $incomplete,
            'absent' => $absent,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attendances(?string $date = null, ?string $employeeId = null, int $limit = 100): array
    {
        $query = Attendance::query()->with('employee:id,name')->latest('recorded_at');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($date) {
            $tz = app(AttendanceWorkingHoursService::class)->timezone();
            $start = Carbon::parse($date, $tz)->startOfDay();
            $end = $start->copy()->endOfDay();
            $query->whereBetween('recorded_at', [$start, $end]);
        }

        return $query->limit($limit)->get()->map(fn (Attendance $a) => [
            'id' => $a->id,
            'employee_id' => $a->employee_id,
            'employee_name' => $a->employee?->name,
            'type' => $a->type?->value,
            'type_label' => $a->type?->label(),
            'recorded_at' => $a->recorded_at?->toIso8601String(),
            'status' => $a->status,
            'is_within_radius' => (bool) $a->is_within_radius,
            'description' => $a->description,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function components(?string $status = 'available', ?string $name = null, int $limit = 100): array
    {
        $query = Component::query()->orderedForList();
        if ($status) {
            $query->where('status', $status);
        }
        if ($name) {
            $query->where('name', $name);
        }

        return $query->limit($limit)->get()->map(fn (Component $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'sn' => $c->sn,
            'supplier' => $c->supplier,
            'invoice_number' => $c->invoice_number,
            'purchase_date' => $c->purchase_date?->toDateString(),
            'status' => $c->status,
        ])->all();
    }

    public function componentStockSummary(): array
    {
        $rows = Component::query()
            ->selectRaw('name, status, COUNT(*) as total')
            ->groupBy('name', 'status')
            ->orderBy('name')
            ->get();

        $byName = [];
        foreach ($rows as $row) {
            $byName[$row->name] ??= [
                'name' => $row->name,
                'available' => 0,
                'used' => 0,
                'damaged' => 0,
                'other' => 0,
                'total' => 0,
            ];
            $bucket = match ($row->status) {
                'available' => 'available',
                'used' => 'used',
                'damaged', 'warranty_claim' => 'damaged',
                default => 'other',
            };
            $byName[$row->name][$bucket] += (int) $row->total;
            $byName[$row->name]['total'] += (int) $row->total;
        }

        return array_values($byName);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assemblies(?string $productType = null, int $limit = 50): array
    {
        $query = Assembly::query()->with('invoice:id,invoice_number,client_name,status')->latest('assembly_date');
        if ($productType) {
            $query->where('product_type', $productType);
        }

        return $query->limit($limit)->get()->map(fn (Assembly $a) => [
            'id' => $a->id,
            'serial_number' => $a->serial_number,
            'product_type' => $a->product_type,
            'assembly_date' => $a->assembly_date?->toDateString(),
            'invoice_number' => $a->invoice?->invoice_number,
            'client_name' => $a->invoice?->client_name,
            'invoice_status' => $a->invoice?->status,
            'sn_details' => $a->sn_details,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoices(?string $status = null, ?string $q = null, int $limit = 50): array
    {
        $query = Invoice::query()->latest('invoice_date');
        if ($status) {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('invoice_number', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('po_number', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (Invoice $i) => [
            'id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'client_name' => $i->client_name,
            'po_number' => $i->po_number,
            'invoice_date' => $i->invoice_date?->toDateString(),
            'paid_date' => $i->paid_date?->toDateString(),
            'total_amount' => (float) $i->total_amount,
            'status' => $i->status,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leaveRequests(?string $status = null, ?string $employeeId = null, int $limit = 50): array
    {
        $query = LeaveRequest::query()->with('employee:id,name')->latest();
        if ($status) {
            $query->where('status', $status);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->limit($limit)->get()->map(fn (LeaveRequest $r) => [
            'id' => $r->id,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->name,
            'type' => $r->type?->value,
            'type_label' => $r->type?->label(),
            'start_date' => $r->start_date?->toDateString(),
            'end_date' => $r->end_date?->toDateString(),
            'hours' => $r->hours,
            'reason' => $r->reason,
            'status' => $r->status,
            'review_notes' => $r->review_notes,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deviceTypes(bool $activeOnly = true): array
    {
        $query = DeviceType::query()->with('components')->ordered();
        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(fn (DeviceType $d) => [
            'id' => $d->id,
            'code' => $d->code,
            'name' => $d->name,
            'description' => $d->description,
            'is_active' => (bool) $d->is_active,
            'components' => $d->components->map(fn ($c) => [
                'slot_key' => $c->slot_key,
                'component_name' => $c->component_name,
                'label' => $c->displayLabel(),
                'is_required' => (bool) $c->is_required,
            ])->values()->all(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function logbooks(?string $from = null, ?string $to = null, ?string $employeeId = null, int $limit = 50): array
    {
        $query = Logbook::query()->with('employee:id,name')->latest('log_date');
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($from) {
            $query->whereDate('log_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('log_date', '<=', $to);
        }

        return $query->limit($limit)->get()->map(fn (Logbook $l) => [
            'id' => $l->id,
            'employee_id' => $l->employee_id,
            'employee_name' => $l->employee?->name,
            'log_date' => $l->log_date instanceof \Carbon\CarbonInterface
                ? $l->log_date->toDateString()
                : (string) $l->log_date,
            'activity' => $l->activity,
            'photo_count' => is_array($l->photo) ? count($l->photo) : 0,
        ])->all();
    }

    public function search(string $q, int $limit = 10): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['query' => $q, 'results' => []];
        }

        return [
            'query' => $q,
            'results' => [
                'employees' => $this->employees($q, false, $limit),
                'tasks' => Task::query()
                    ->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->limit($limit)
                    ->get(['id', 'title', 'status', 'start_date', 'end_date'])
                    ->map(fn (Task $t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'status' => $t->status,
                        'start_date' => $t->start_date?->toDateString(),
                        'end_date' => $t->end_date?->toDateString(),
                    ])->all(),
                'invoices' => $this->invoices(null, $q, $limit),
                'assemblies' => Assembly::query()
                    ->where('serial_number', 'like', "%{$q}%")
                    ->orWhere('product_type', 'like', "%{$q}%")
                    ->limit($limit)
                    ->get(['id', 'serial_number', 'product_type', 'assembly_date'])
                    ->map(fn (Assembly $a) => [
                        'id' => $a->id,
                        'serial_number' => $a->serial_number,
                        'product_type' => $a->product_type,
                        'assembly_date' => $a->assembly_date?->toDateString(),
                    ])->all(),
                'components' => Component::query()
                    ->where('sn', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->limit($limit)
                    ->get(['id', 'name', 'sn', 'status'])
                    ->map(fn (Component $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'sn' => $c->sn,
                        'status' => $c->status,
                    ])->all(),
            ],
        ];
    }

    private function serializeEmployee(Employee $employee, bool $detailed = false): array
    {
        $data = [
            'id' => $employee->id,
            'name' => $employee->name,
            'nik' => $employee->nik,
            'position' => $employee->position,
            'role' => $employee->role?->value,
            'role_label' => $employee->role?->label(),
            'phone_number' => $employee->phone_number,
            'is_active' => (bool) $employee->is_active,
            'telegram_linked' => filled($employee->telegram_chat_id),
        ];

        if ($detailed) {
            $data['open_tasks_count'] = $employee->tasks()
                ->whereIn('status', ['pending', 'in_progress', 'late'])
                ->count();
        }

        // Salary intentionally omitted from agent API by default.
        return $data;
    }
}
