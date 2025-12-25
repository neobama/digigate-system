<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'vendor_invoice_number',
        'description',
        'account_code',
        'fund_source',
        'expense_date',
        'amount',
        'proof_of_payment',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];
}

