<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasUuids, LogsActivity;
    
    protected $fillable = [
        'budget_request_id',
        'cashbon_id',
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

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function cashbon()
    {
        return $this->belongsTo(Cashbon::class);
    }
}

