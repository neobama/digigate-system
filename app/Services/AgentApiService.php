<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Assembly;
use App\Models\Attendance;
use App\Models\BudgetRequest;
use App\Models\Cashbon;
use App\Models\Component;
use App\Models\DeviceReturn;
use App\Models\DeviceType;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Logbook;
use App\Models\Reimbursement;
use App\Models\SalaryPayment;
use App\Models\Task;
use App\Models\WarrantyClaim;
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
                'reimbursements_pending' => Reimbursement::query()->where('status', 'pending')->count(),
                'cashbons_pending' => Cashbon::query()->where('status', 'pending')->count(),
                'budget_requests_pending' => BudgetRequest::query()->where('status', 'pending')->count(),
                'salary_payments_unpaid' => SalaryPayment::query()->where('status', '!=', 'paid')->count(),
                'warranty_open' => WarrantyClaim::query()->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'expenses_this_month' => Expense::query()
                    ->whereYear('expense_date', now($tz)->year)
                    ->whereMonth('expense_date', now($tz)->month)
                    ->sum('amount'),
                'income_this_month' => Income::query()
                    ->whereYear('income_date', now($tz)->year)
                    ->whereMonth('income_date', now($tz)->month)
                    ->sum('amount'),
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
                'expenses' => Expense::query()
                    ->where('description', 'like', "%{$q}%")
                    ->orWhere('vendor_invoice_number', 'like', "%{$q}%")
                    ->limit($limit)
                    ->get()
                    ->map(fn (Expense $e) => $this->serializeExpense($e))
                    ->all(),
                'reimbursements' => Reimbursement::query()
                    ->with('employee:id,name')
                    ->where('purpose', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->limit($limit)
                    ->get()
                    ->map(fn (Reimbursement $r) => $this->serializeReimbursement($r))
                    ->all(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expenses(?string $from = null, ?string $to = null, ?string $fundSource = null, ?string $q = null, int $limit = 100): array
    {
        $query = Expense::query()->latest('expense_date');
        if ($from) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('expense_date', '<=', $to);
        }
        if ($fundSource) {
            $query->where('fund_source', $fundSource);
        }
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('description', 'like', "%{$q}%")
                    ->orWhere('vendor_invoice_number', 'like', "%{$q}%")
                    ->orWhere('account_code', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (Expense $e) => $this->serializeExpense($e))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomes(?string $from = null, ?string $to = null, ?string $q = null, int $limit = 100): array
    {
        $query = Income::query()->with('invoice:id,invoice_number,client_name')->latest('income_date');
        if ($from) {
            $query->whereDate('income_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('income_date', '<=', $to);
        }
        if ($q) {
            $query->where('description', 'like', "%{$q}%");
        }

        return $query->limit($limit)->get()->map(fn (Income $i) => [
            'id' => $i->id,
            'description' => $i->description,
            'income_date' => $i->income_date?->toDateString(),
            'amount' => (float) $i->amount,
            'invoice_id' => $i->invoice_id,
            'invoice_number' => $i->invoice?->invoice_number,
            'client_name' => $i->invoice?->client_name,
            'is_from_invoice' => $i->isFromInvoice(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reimbursements(?string $status = null, ?string $employeeId = null, int $limit = 100): array
    {
        $query = Reimbursement::query()->with('employee:id,name')->latest('expense_date');
        if ($status) {
            $query->where('status', $status);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->limit($limit)->get()->map(fn (Reimbursement $r) => $this->serializeReimbursement($r))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cashbons(?string $status = null, ?string $employeeId = null, int $limit = 100): array
    {
        $query = Cashbon::query()->with('employee:id,name')->latest('request_date');
        if ($status) {
            $query->where('status', $status);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->limit($limit)->get()->map(fn (Cashbon $c) => [
            'id' => $c->id,
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->name,
            'amount' => (float) $c->amount,
            'reason' => $c->reason,
            'request_date' => $c->request_date?->toDateString(),
            'status' => $c->status,
            'installment_months' => $c->installment_months,
            'is_term_loan' => (bool) $c->is_term_loan,
            'paid_at' => $c->paid_at?->toIso8601String(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function budgetRequests(?string $status = null, ?string $employeeId = null, int $limit = 100): array
    {
        $query = BudgetRequest::query()->with('employee:id,name')->latest('request_date');
        if ($status) {
            $query->where('status', $status);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->limit($limit)->get()->map(fn (BudgetRequest $b) => [
            'id' => $b->id,
            'employee_id' => $b->employee_id,
            'employee_name' => $b->employee?->name,
            'budget_name' => $b->budget_name,
            'budget_detail' => $b->budget_detail,
            'recipient_account' => $b->recipient_account,
            'amount' => (float) $b->amount,
            'status' => $b->status,
            'request_date' => $b->request_date?->toDateString(),
            'paid_at' => $b->paid_at?->toIso8601String(),
            'realized_amount' => $b->realized_amount !== null ? (float) $b->realized_amount : null,
            'realization_notes' => $b->realization_notes,
            'realization_submitted_at' => $b->realization_submitted_at?->toIso8601String(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salaryPayments(?int $month = null, ?int $year = null, ?string $employeeId = null, ?string $status = null, int $limit = 100): array
    {
        $query = SalaryPayment::query()
            ->with(['employee:id,name', 'adjustments'])
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($month) {
            $query->where('month', $month);
        }
        if ($year) {
            $query->where('year', $year);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $query->limit($limit)->get()->map(fn (SalaryPayment $s) => [
            'id' => $s->id,
            'employee_id' => $s->employee_id,
            'employee_name' => $s->employee?->name,
            'month' => (int) $s->month,
            'year' => (int) $s->year,
            'base_salary' => (float) $s->base_salary,
            'total_cashbon' => (float) $s->total_cashbon,
            'bpjs_allowance' => (float) $s->bpjs_allowance,
            'days_present' => (int) $s->days_present,
            'attendance_deduction' => (float) $s->attendance_deduction,
            'apply_attendance_deduction' => (bool) $s->apply_attendance_deduction,
            'adjustment_addition' => (float) $s->adjustment_addition,
            'adjustment_deduction' => (float) $s->adjustment_deduction,
            'adjustment_note' => $s->adjustment_note,
            'net_salary' => (float) $s->net_salary,
            'fund_source' => $s->fund_source,
            'status' => $s->status,
            'paid_at' => $s->paid_at?->toIso8601String(),
            'adjustments' => $s->adjustments->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'description' => $a->description,
                'amount' => (float) $a->amount,
            ])->values()->all(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function warrantyClaims(?string $status = null, ?string $q = null, int $limit = 100): array
    {
        $query = WarrantyClaim::query()->latest('entry_date');
        if ($status) {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('assembly_serial_number', 'like', "%{$q}%")
                    ->orWhere('invoice_number', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (WarrantyClaim $w) => [
            'id' => $w->id,
            'ticket_number' => $w->ticket_number,
            'invoice_number' => $w->invoice_number,
            'invoice_date' => $w->invoice_date?->toDateString(),
            'claim_category' => $w->claim_category,
            'client_name' => $w->client_name,
            'phone_number' => $w->phone_number,
            'device_type' => $w->device_type,
            'complaint' => $w->complaint,
            'assembly_serial_number' => $w->assembly_serial_number,
            'is_assembly_linked' => (bool) $w->is_assembly_linked,
            'source' => $w->source,
            'status' => $w->status,
            'entry_date' => $w->entry_date?->toIso8601String(),
            'completed_at' => $w->completed_at?->toIso8601String(),
            'notes' => $w->notes,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deviceReturns(?string $status = null, ?string $q = null, int $limit = 100): array
    {
        $query = DeviceReturn::query()->latest();
        if ($status) {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('tracking_number', 'like', "%{$q}%")
                    ->orWhere('invoice_number', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (DeviceReturn $d) => [
            'id' => $d->id,
            'tracking_number' => $d->tracking_number,
            'invoice_number' => $d->invoice_number,
            'purchase_date' => $d->purchase_date?->toDateString(),
            'device_type' => $d->device_type,
            'serial_number' => $d->serial_number,
            'include_mikrotik_license' => (bool) $d->include_mikrotik_license,
            'customer_name' => $d->customer_name,
            'company_name' => $d->company_name,
            'phone_number' => $d->phone_number,
            'issue_details' => $d->issue_details,
            'status' => $d->status,
            'proof_file_count' => is_array($d->proof_files) ? count($d->proof_files) : 0,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documents(?string $category = null, ?string $q = null, int $limit = 100): array
    {
        $query = Document::query()->latest();
        if ($category) {
            $query->where('category', $category);
        }
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('file_name', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (Document $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'file_name' => $d->file_name,
            'mime_type' => $d->mime_type,
            'file_size' => $d->file_size,
            'category' => $d->category,
            'description' => $d->description,
            'related_invoice_id' => $d->related_invoice_id,
            'access_level' => $d->access_level,
            'created_at' => $d->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activityLogs(?string $action = null, ?string $modelType = null, ?string $q = null, int $limit = 100): array
    {
        $query = ActivityLog::query()->latest();
        if ($action) {
            $query->where('action', $action);
        }
        if ($modelType) {
            $query->where('model_type', 'like', "%{$modelType}%");
        }
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('description', 'like', "%{$q}%")
                    ->orWhere('user_name', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (ActivityLog $log) => [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_name' => $log->user_name,
            'action' => $log->action,
            'description' => $log->description,
            'model_type' => $log->model_type,
            'model_id' => $log->model_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->all();
    }

    public function financialSummary(?int $month = null, ?int $year = null): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = now($tz);
        $month ??= (int) $now->month;
        $year ??= (int) $now->year;

        $income = (float) Income::query()
            ->whereYear('income_date', $year)
            ->whereMonth('income_date', $month)
            ->sum('amount');
        $expense = (float) Expense::query()
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->sum('amount');

        return [
            'month' => $month,
            'year' => $year,
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => $income - $expense,
            'reimbursements_paid' => (float) Reimbursement::query()
                ->where('status', 'paid')
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $month)
                ->sum('amount'),
            'cashbons_paid' => (float) Cashbon::query()
                ->where('status', 'paid')
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $month)
                ->sum('amount'),
            'salaries_paid' => (float) SalaryPayment::query()
                ->where('status', 'paid')
                ->where('month', $month)
                ->where('year', $year)
                ->sum('net_salary'),
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
            'base_salary' => (float) $employee->base_salary,
            'bpjs_allowance' => (float) $employee->bpjs_allowance,
            'birth_date' => $employee->birth_date?->toDateString(),
        ];

        if ($detailed) {
            $data['open_tasks_count'] = $employee->tasks()
                ->whereIn('status', ['pending', 'in_progress', 'late'])
                ->count();
            $data['remaining_cashbon_allowance'] = $employee->getRemainingCashbonAllowance();
        }

        return $data;
    }

    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'vendor_invoice_number' => $expense->vendor_invoice_number,
            'account_code' => $expense->account_code,
            'fund_source' => $expense->fund_source,
            'expense_date' => $expense->expense_date?->toDateString(),
            'amount' => (float) $expense->amount,
            'budget_request_id' => $expense->budget_request_id,
            'cashbon_id' => $expense->cashbon_id,
            'reimbursement_id' => $expense->reimbursement_id,
            'has_proof' => filled($expense->proof_of_payment),
        ];
    }

    private function serializeReimbursement(Reimbursement $r): array
    {
        return [
            'id' => $r->id,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->name,
            'purpose' => $r->purpose,
            'expense_date' => $r->expense_date?->toDateString(),
            'amount' => (float) $r->amount,
            'description' => $r->description,
            'status' => $r->status,
            'paid_at' => $r->paid_at?->toIso8601String(),
            'has_proof' => filled($r->proof_of_payment),
        ];
    }
}
