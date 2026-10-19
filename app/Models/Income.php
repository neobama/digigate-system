<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id',
        'description',
        'income_date',
        'amount',
    ];

    protected $casts = [
        'income_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isFromInvoice(): bool
    {
        return filled($this->invoice_id);
    }
}
