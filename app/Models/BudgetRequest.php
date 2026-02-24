<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
