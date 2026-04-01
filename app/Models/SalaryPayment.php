<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'base_salary',
        'total_cashbon',
        'bpjs_allowance',
        'adjustment_addition',
        'adjustment_deduction',
        'adjustment_note',
        'net_salary',
        'fund_source',
        'status',
        'paid_at',
        'expense_id',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'total_cashbon' => 'decimal:2',
        'bpjs_allowance' => 'decimal:2',
        'adjustment_addition' => 'decimal:2',
        'adjustment_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SalaryPaymentAdjustment::class);
    }
}

