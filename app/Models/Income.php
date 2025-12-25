<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'description',
        'income_date',
        'amount',
    ];

    protected $casts = [
        'income_date' => 'date',
        'amount' => 'decimal:2',
    ];
}

