<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Cashbon extends Model
{
    use HasUuids;
    protected $fillable = ['employee_id', 'amount', 'reason', 'request_date', 'status', 'installment_months'];
    
    protected $casts = [
        'installment_months' => 'integer',
        'request_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
