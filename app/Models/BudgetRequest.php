<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BudgetRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'employee_id',
        'budget_name',
        'budget_detail',
        'invoice',
        'recipient_account',
        'amount',
        'status',
        'proof_of_payment',
        'request_date',
        'paid_at',
        'realized_amount',
        'realization_notes',
        'realization_proof_images',
        'realization_submitted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'realized_amount' => 'decimal:2',
        'request_date' => 'date',
        'paid_at' => 'datetime',
        'realization_proof_images' => 'array',
        'realization_submitted_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    public function needsRealization(): bool
    {
        return $this->status === 'paid'
            && $this->realization_submitted_at === null;
    }
}
