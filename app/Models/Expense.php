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
        'reimbursement_id',
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

    public function reimbursement()
    {
        return $this->belongsTo(Reimbursement::class);
    }

    public function isLinkedToSource(): bool
    {
        return filled($this->cashbon_id)
            || filled($this->budget_request_id)
            || filled($this->reimbursement_id)
            || $this->account_code === 'GAJI';
    }

    public function sourceLabel(): string
    {
        if ($this->cashbon_id) {
            return 'Cashbon';
        }
        if ($this->budget_request_id) {
            return 'Request Anggaran';
        }
        if ($this->reimbursement_id) {
            return 'Reimbursement';
        }
        if ($this->account_code === 'GAJI') {
            return 'Gaji';
        }

        return 'Manual';
    }
}

