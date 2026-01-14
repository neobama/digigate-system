<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'is_self_assigned',
        'proof_images',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_self_assigned' => 'boolean',
        'proof_images' => 'array',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_employee');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
