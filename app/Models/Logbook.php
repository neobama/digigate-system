<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Logbook extends Model
{
    use HasUuids;
    protected $fillable = ['employee_id', 'log_date', 'activity', 'photo'];

    protected $casts = [
        'photo' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'logbook_employees', 'logbook_id', 'employee_id')
            ->withTimestamps();
    }
}
