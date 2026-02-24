<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use HasUuids;
    
    protected $fillable = ['employee_id', 'purpose', 'expense_date', 'amount', 'proof_of_payment', 'description', 'status', 'paid_at'];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

