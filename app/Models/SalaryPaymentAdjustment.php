<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPaymentAdjustment extends Model
{
    use HasUuids;

    protected $fillable = [
        'salary_payment_id',
        'type',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function salaryPayment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class);
    }
}

